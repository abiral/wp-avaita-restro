+(function ($) {
    'use strict';

    // ----- Helpers ---------------------------------------------------------

    function normalizeWhitespace(value) {
        if (value === null || value === undefined) return '';
        return String(value).trim().replace(/\s+/g, ' ');
    }

    function titleCase(value) {
        var s = normalizeWhitespace(value);
        if (!s) return '';
        return s.split(' ').map(function (word) {
            if (!word) return '';
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }).join(' ');
    }

    // Minimal CSV parser. Handles:
    //  - quoted fields with embedded commas and newlines
    //  - escaped quotes ("") inside a quoted field
    //  - both \r\n and \n line endings
    //  - leading UTF-8 BOM on the file
    function parseCSV(text) {
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.slice(1);
        }

        var rows = [];
        var row = [];
        var field = '';
        var inQuotes = false;
        var i = 0;
        var len = text.length;

        while (i < len) {
            var ch = text.charAt(i);

            if (inQuotes) {
                if (ch === '"') {
                    if (i + 1 < len && text.charAt(i + 1) === '"') {
                        field += '"';
                        i += 2;
                        continue;
                    }
                    inQuotes = false;
                    i++;
                    continue;
                }
                field += ch;
                i++;
                continue;
            }

            if (ch === '"') {
                inQuotes = true;
                i++;
                continue;
            }

            if (ch === ',') {
                row.push(field);
                field = '';
                i++;
                continue;
            }

            if (ch === '\r') {
                // swallow; next char (\n) will terminate the line
                i++;
                continue;
            }

            if (ch === '\n') {
                row.push(field);
                rows.push(row);
                row = [];
                field = '';
                i++;
                continue;
            }

            field += ch;
            i++;
        }

        // flush trailing field/row (file might not end with a newline)
        if (field !== '' || row.length > 0) {
            row.push(field);
            rows.push(row);
        }

        // drop fully-blank trailing rows
        while (rows.length && rows[rows.length - 1].length === 1 && rows[rows.length - 1][0] === '') {
            rows.pop();
        }

        return rows;
    }

    function rowsToObjects(rows) {
        if (rows.length === 0) return [];
        var headers = rows[0].map(function (h) { return normalizeWhitespace(h).toLowerCase(); });
        var out = [];
        for (var i = 1; i < rows.length; i++) {
            var obj = {};
            for (var j = 0; j < headers.length; j++) {
                obj[headers[j]] = rows[i][j] !== undefined ? rows[i][j] : '';
            }
            out.push(obj);
        }
        return out;
    }

    function getCalc() {
        if (window.AvaitaDelivery && typeof window.AvaitaDelivery.calculateDeliveryCharge === 'function') {
            return window.AvaitaDelivery.calculateDeliveryCharge;
        }
        return null;
    }

    function buildRow(raw, index, errors) {
        var rowNum = index + 2; // +1 for 1-based, +1 for header row

        var area     = titleCase(raw.area);
        var sub_area = titleCase(raw.sub_area);
        var street   = titleCase(raw.street);
        var city     = titleCase(raw.city);
        var state    = normalizeWhitespace(raw.state) || 'LUM';
        var distanceRaw = normalizeWhitespace(raw.distance);

        // Optional id column. 0 means "no id" -> server upserts by location.
        var idRaw = normalizeWhitespace(raw.id);
        var id = 0;
        if (idRaw !== '') {
            id = parseInt(idRaw, 10);
            if (isNaN(id) || id <= 0) { id = 0; } // malformed id -> treat as no id
        }

        if (!area) {
            errors.push({ row: rowNum, message: 'Missing required field: area' });
            return null;
        }
        if (!sub_area) {
            errors.push({ row: rowNum, message: 'Missing required field: sub_area' });
            return null;
        }
        if (!city) {
            errors.push({ row: rowNum, message: 'Missing required field: city' });
            return null;
        }

        var distance = parseFloat(distanceRaw);
        if (isNaN(distance) || distance <= 0) {
            errors.push({ row: rowNum, message: 'Invalid distance: "' + distanceRaw + '"' });
            return null;
        }

        var calc = getCalc();
        if (!calc) {
            errors.push({ row: rowNum, message: 'Calculation function not loaded' });
            return null;
        }

        var values = calc(distance);
        if (!values) {
            errors.push({ row: rowNum, message: 'Distance ' + distance + ' km is out of supported range' });
            return null;
        }

        return {
            id: id,
            area: area,
            sub_area: sub_area,
            street: street,
            city: city,
            state: state.toUpperCase(),
            distance: distance,
            minimum_order_threshold: parseFloat(values.minimum_order_acceptance),
            minimum_free_delivery: parseFloat(values.minimum_price_for_delivery),
            delivery_price: parseFloat(values.total_delivery_charge),
        };
    }

    // ----- UI feedback (mirrors helpers in admin-local-delivery-form.js) ---

    function showNotice(type, message) {
        var html = '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>';
        $('#avaita-admin-messages').html(html);
    }

    function blockUI() {
        if (typeof tb_show === 'function') {
            tb_show('', '#TB_inline?height=240&amp;width=405&amp;inlineId=avaita-loader&amp;modal=true', null);
        }
    }

    function unBlockUI() {
        if (typeof tb_remove === 'function') {
            tb_remove();
        }
    }

    // ----- Import pipeline -------------------------------------------------

    function handleFile(file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                processCsvText(e.target.result);
            } catch (err) {
                showNotice('error', 'Failed to read CSV: ' + (err && err.message ? err.message : err));
            }
        };
        reader.onerror = function () {
            showNotice('error', 'Failed to read the selected file.');
        };
        reader.readAsText(file);
    }

    function processCsvText(text) {
        var rows = parseCSV(text);
        if (rows.length < 2) {
            showNotice('error', 'CSV has no data rows. Expected a header row followed by at least one data row.');
            return;
        }

        var objects = rowsToObjects(rows);

        var errors = [];
        var validated = [];
        for (var i = 0; i < objects.length; i++) {
            var row = buildRow(objects[i], i, errors);
            if (row) validated.push(row);
        }

        if (validated.length === 0) {
            showNotice('error', 'No valid rows in CSV. ' + errors.length + ' row(s) had errors.');
            return;
        }

        var msg = 'Import ' + validated.length + ' row(s)?';
        var idRows = validated.filter(function (r) { return r.id > 0; }).length;
        if (idRows > 0) {
            msg += '\n\n⚠ ' + idRows + ' row(s) include an id and will UPDATE the matching '
                 + 'existing record (or be inserted if the id is not found).';
        }
        if (errors.length > 0) {
            msg += '\n\n' + errors.length + ' row(s) will be skipped due to errors:\n';
            msg += errors.slice(0, 5).map(function (e) {
                return '  • Row ' + e.row + ': ' + e.message;
            }).join('\n');
            if (errors.length > 5) {
                msg += '\n  • …and ' + (errors.length - 5) + ' more.';
            }
        }

        if (!window.confirm(msg)) {
            return;
        }

        sendImport(validated, errors);
    }

    function sendImport(rows, clientErrors) {
        blockUI();

        $.ajax({
            url: AVAITA_DELIVERY_VARS.import_url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ rows: rows }),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', AVAITA_DELIVERY_VARS.nonce);
            },
            success: function (response) {
                unBlockUI();

                var inserted = response.inserted || 0;
                var updated  = response.updated || 0;
                var skipped  = (response.skipped || 0) + clientErrors.length;
                var serverErrors = response.errors || [];
                var allErrors = clientErrors.concat(serverErrors);

                var parts = [];
                parts.push(inserted + ' inserted');
                parts.push(updated + ' updated');
                if (skipped) parts.push(skipped + ' skipped');

                var html = '<strong>Import complete:</strong> ' + parts.join(', ') + '.';

                if (allErrors.length) {
                    html += '<br><br><strong>Errors:</strong><ul style="margin: 6px 0 0 18px; list-style: disc;">';
                    allErrors.slice(0, 10).forEach(function (e) {
                        html += '<li>Row ' + e.row + ': ' + (e.message || 'Unknown error') + '</li>';
                    });
                    if (allErrors.length > 10) {
                        html += '<li>…and ' + (allErrors.length - 10) + ' more.</li>';
                    }
                    html += '</ul>';
                }

                showNotice(allErrors.length ? 'warning' : 'success', html);

                if (inserted > 0 || updated > 0) {
                    setTimeout(function () { location.reload(); }, 2500);
                }
            },
            error: function (xhr) {
                unBlockUI();
                var msg = 'Import failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg += ' ' + xhr.responseJSON.message;
                }
                showNotice('error', msg);
            }
        });
    }

    // ----- Wire up ---------------------------------------------------------

    $(document).ready(function () {
        $(document).on('click', '.avaita-import-trigger', function (e) {
            e.preventDefault();
            $('.avaita-import-file').val('').trigger('click');
        });

        $(document).on('change', '.avaita-import-file', function (e) {
            var file = e.target.files && e.target.files[0];
            if (!file) return;
            handleFile(file);
        });
    });

})(jQuery);
