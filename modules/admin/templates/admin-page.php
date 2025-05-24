<div class="wrap">
    <h1>Avaita Restro</h1>
    <h2 class="nav-tab-wrapper">
        <?php foreach($tabs as $key => $tab): ?>
            <a href="<?php echo $tab['url']; ?>" class="nav-tab<?php echo $key == $active_tab? ' nav-tab-active' : ''; ?>"><?php echo $tab['title']; ?></a>
        <?php endforeach; ?>
    </h2>

    <?php do_action('avaita_admin_page', $active_tab); ?>
    <?php // require_once __DIR__ . '/' . str_replace('_','-', $active_tab) . '-settings.php'; ?>
       
    <?php /* ?>
    <form method="post" action="options.php">
        <?php settings_fields('tabbed_settings'); ?>
        <?php submit_button(); ?>
    </form>
    <?php */ ?>
</div>