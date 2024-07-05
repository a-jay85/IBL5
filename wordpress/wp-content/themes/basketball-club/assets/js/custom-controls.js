(function(api) {

    api.sectionConstructor['basketball-club-upsell'] = api.Section.extend({
        attachEvents: function() {},
        isContextuallyActive: function() {
            return true;
        }
    });

    const basketball_club_section_lists = ['banner', 'service'];
    basketball_club_section_lists.forEach(basketball_club_homepage_scroll);

    function basketball_club_homepage_scroll(item, index) {
        item = item.replace(/-/g, '_');
        wp.customize.section('basketball_club_' + item + '_section', function(section) {
            section.expanded.bind(function(isExpanding) {
                wp.customize.previewer.send(item, { expanded: isExpanding });
            });
        });
    }
})(wp.customize);