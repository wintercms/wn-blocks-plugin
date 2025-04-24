(($) => {
    $.wn.cmsPage.updateModifiedCounter = function () {
        var counters = {
            page: {menu: 'pages', count: 0},
            partial: {menu: 'partials', count: 0},
            layout: {menu: 'layouts', count: 0},
            content: {menu: 'content', count: 0},
            asset: {menu: 'assets', count: 0},
            block: {menu: 'blocks', count: 0},
        }

        $('> div.tab-content > div.tab-pane[data-modified]', '#cms-master-tabs').each(function () {
            var inputType = $('> form > input[name=templateType]', this).val();
            counters[inputType].count++;
        });

        $.each(counters, function (type, data) {
            $.wn.sideNav.setCounter('cms/' + data.menu, data.count);
        });
    };
})(window.jQuery);
