/**
 * Avaita Local Delivery — block checkout address selector.
 *
 * Renders the cascading City -> Area -> Sub Area -> Street selector inside the
 * WooCommerce block checkout (via the ExperimentalOrderShippingPackages slot)
 * and pushes the chosen location to the server with extensionCartUpdate so the
 * Avaita_Local_Shipping_Method can price it. Authored in plain JS against the
 * global wc/wp objects — no build step.
 */
(function () {
    var wcCheckout = window.wc && window.wc.blocksCheckout;
    var wp = window.wp;

    if (!wcCheckout || !wp || !wp.element || !wp.plugins) {
        return;
    }

    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var registerPlugin = wp.plugins.registerPlugin;
    var extensionCartUpdate = wcCheckout.extensionCartUpdate;
    var Slot = wcCheckout.ExperimentalOrderShippingPackages || wcCheckout.ExperimentalOrderMeta;

    var vars = window.AVAITA_DELIVERY_BLOCK || {};
    var apiHost = vars.apiHost || '';
    var labels = vars.labels || {};
    var initial = vars.selection || {};
    var cities = vars.cities || [];

    if (!Slot || typeof extensionCartUpdate !== 'function') {
        return;
    }

    function fetchOptions(path, key) {
        return fetch(apiHost + path)
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (data) {
                if (!Array.isArray(data)) { return []; }
                return data.map(function (row) { return row[key]; });
            })
            .catch(function () { return []; });
    }

    function field(label, value, options, onChange, disabled) {
        var children = [el('option', { value: '', key: '__ph' }, label)];
        options.forEach(function (opt) {
            children.push(el('option', { value: opt, key: opt }, opt));
        });
        return el(
            'p',
            { className: 'avaita-block-field', style: { margin: '0 0 12px' } },
            el(
                'select',
                {
                    className: 'avaita-block-select',
                    style: { width: '100%', padding: '8px', boxSizing: 'border-box' },
                    value: value,
                    disabled: disabled,
                    onChange: function (e) { onChange(e.target.value); }
                },
                children
            )
        );
    }

    function Selector() {
        var c = useState(initial.city || ''), city = c[0], setCity = c[1];
        var a = useState(initial.area || ''), area = a[0], setArea = a[1];
        var s = useState(initial.sub_area || ''), subArea = s[0], setSubArea = s[1];
        var t = useState(initial.street || ''), street = t[0], setStreet = t[1];

        var ao = useState([]), areas = ao[0], setAreas = ao[1];
        var so = useState([]), subAreas = so[0], setSubAreas = so[1];
        var to = useState([]), streets = to[0], setStreets = to[1];

        useEffect(function () {
            if (!city) { setAreas([]); return; }
            fetchOptions('/delivery-addresses/areas?city=' + encodeURIComponent(city), 'area').then(setAreas);
        }, [city]);

        useEffect(function () {
            if (!city || !area) { setSubAreas([]); return; }
            fetchOptions(
                '/delivery-addresses/sub-areas?city=' + encodeURIComponent(city) + '&area=' + encodeURIComponent(area),
                'sub_area'
            ).then(setSubAreas);
        }, [city, area]);

        useEffect(function () {
            if (!city || !area || !subArea) { setStreets([]); return; }
            fetchOptions(
                '/delivery-addresses/street?city=' + encodeURIComponent(city) +
                    '&area=' + encodeURIComponent(area) +
                    '&sub_area=' + encodeURIComponent(subArea),
                'street'
            ).then(setStreets);
        }, [city, area, subArea]);

        // Once a full location is chosen, push it to the Store API so the
        // shipping method can price it (and the cart/shipping refreshes).
        useEffect(function () {
            if (city && area && subArea && street) {
                extensionCartUpdate({
                    namespace: 'avaita-local-delivery',
                    data: { city: city, area: area, sub_area: subArea, street: street }
                });
            }
        }, [city, area, subArea, street]);

        return el(
            'div',
            { className: 'avaita-delivery-block' },
            el('p', { className: 'avaita-delivery-block__title', style: { fontWeight: 600, margin: '0 0 8px' } }, labels.title || 'Delivery location'),
            field(labels.city || 'City', city, cities, function (v) { setCity(v); setArea(''); setSubArea(''); setStreet(''); }, false),
            field(labels.area || 'Area', area, areas, function (v) { setArea(v); setSubArea(''); setStreet(''); }, !city),
            field(labels.sub_area || 'Sub Area', subArea, subAreas, function (v) { setSubArea(v); setStreet(''); }, !area),
            field(labels.street || 'Street', street, streets, function (v) { setStreet(v); }, !subArea)
        );
    }

    function Render() {
        return el(Slot, {}, el(Selector, {}));
    }

    registerPlugin('avaita-local-delivery', {
        render: Render,
        scope: 'woocommerce-checkout'
    });
})();
