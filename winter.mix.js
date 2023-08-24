const mix = require('laravel-mix');

mix
    .setPublicPath(__dirname)
    .js('assets/src/js/blocks.js', 'assets/dist/js/blocks.js')
    .less('formwidgets/blocks/assets/less/blocks.less', 'formwidgets/blocks/assets/css/blocks.css');
