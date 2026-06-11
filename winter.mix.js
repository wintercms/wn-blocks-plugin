const mix = require('laravel-mix');

/*
 * NOTE: there are two distinct blocks.js files — do not confuse or merge them:
 *
 *   assets/dist/js/blocks.js            (built here from assets/src/js/blocks.js)
 *     The FRONTEND Snowboard "actions" build. Loaded manually in the theme after
 *     Snowboard (see README "Actions").
 *
 *   formwidgets/blocks/assets/js/blocks.js  (hand-maintained, not built here)
 *     The BACKEND FormWidget behaviour (jQuery "fieldBlocks" plugin), served via
 *     addJs() in formwidgets/Blocks.php. This is the one the editor UI uses.
 *
 * They share a filename but target different runtimes.
 */
mix
    .setPublicPath(__dirname)
    .js('assets/src/js/blocks.js', 'assets/dist/js/blocks.js')
    .less('formwidgets/blocks/assets/less/blocks.less', 'formwidgets/blocks/assets/css/blocks.css');
