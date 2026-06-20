const mix = require('laravel-mix');

mix
    .setPublicPath(__dirname)
    .js('assets/src/js/blocks.js', 'assets/dist/js/blocks.js')
    .js('assets/src/js/winter.cmspage.extension.js', 'assets/dist/js/winter.cmspage.extension.js')
    .less('formwidgets/blocks/assets/less/blocks.less', 'formwidgets/blocks/assets/css/blocks.css');
