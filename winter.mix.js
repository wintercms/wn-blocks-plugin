const mix = require('laravel-mix');

/*
 * Two separate blocks.js files exist for different runtimes:
 *   - assets/dist/js/blocks.js: frontend Snowboard "actions" build, compiled
 *     here from assets/src/js/blocks.js.
 *   - formwidgets/blocks/assets/js/blocks.js: backend FormWidget script (jQuery
 *     "fieldBlocks"), maintained by hand and served via addJs() in Blocks.php.
 */
mix
    .setPublicPath(__dirname)
    .js('assets/src/js/blocks.js', 'assets/dist/js/blocks.js')
    .less('formwidgets/blocks/assets/less/blocks.less', 'formwidgets/blocks/assets/css/blocks.css');
