/**
 * A very straightforward lightbox tool that autowires based on links
 * having the target "lightbox".
 * 
 * By default all items on the page are looped through, but groups can
 * be specified by wrapping links in elements with an attribute like
 * data-lightbox-group="groupname"
 * 
 * If multiple wrappers share a data-lightbox-group name, their items
 * will be shared, and opening one will allow traversing to the other
 * using the arrows.
 */
$(() => {
    $('body').append('\
    <div id="lightbox" class="colors-dark" role="dialog" aria-label="Lightbox">\
    <div id="lightbox-content"></div>\
    <div id="lightbox-controls"><a class="previous" role="button">previous</a> <a class="next" role="button">next</a> <a class="close" role="button">close</a></div>\
    <div id="lightbox-loading"><div class="spinner"></div></div>\
    </div>');

    var group = $('body a[target="lightbox"]');
    var groupName = 'body';
    var groupSelector = 'body'
    var currentPos = 0;
    var nextPos = 0;
    var previousPos = 0;
    var content;

    /**
     * Events for hiding/showing lightbox
     */
    $(document).on('lightbox:hide', (e) => {
        $('#lightbox').removeClass('active');
    });
    $(document).on('lightbox:show', (e) => {
        $('#lightbox').addClass('active');
    });

    /**
     * Events for hiding/showing loading overlay
     */
    $(document).on('lightbox:loading', (e) => {
        $('#lightbox-loading').addClass('active');
    });
    $(document).on('lightbox:loaded', (e) => {
        $('#lightbox-loading').removeClass('active');
    });
    $('#lightbox-controls .close').on('click', () => { $(document).trigger('lightbox:hide') });

    /**
     * Events for going forward/backward
     */
    $(document).on('lightbox:next', (e) => {
        if ($('#lightbox.active')) {
            group.eq(nextPos).trigger('click');
        }
    });
    $(document).on('lightbox:previous', (e) => {
        if ($('#lightbox.active')) {
            group.eq(previousPos).trigger('click');
        }
    });
    $('#lightbox-controls .next').on('click', () => { $(document).trigger('lightbox:next') });
    $('#lightbox-controls .previous').on('click', () => { $(document).trigger('lightbox:previous') });

    /**
     * Auto-opener, picks a good way based on URL
     */
    $(document).on('lightbox:open:auto', (e, url) => {
        if (/\.(jpe?g|tiff?|w?bmp|xbm|png|gif|webp|xbm)$/.test(url)) {
            $(document).trigger('lightbox:open:image', [url]);
        } else {
            $(document).trigger('lightbox:open:iframe', [url]);
        }
    });

    /**
     * Open content in an iframe
     */
    $(document).on('lightbox:open:iframe', (e, url) => {
        $(document).trigger('lightbox:loading');
        content = $(document.createElement('iframe'));
        content.on('load', () => { $(document).trigger('lightbox:loaded') });
        $('#lightbox-content').html('');
        $('#lightbox-content').append(content);
        content.attr('src', url);
    });

    /**
     * Open content as an image
     */
    $(document).on('lightbox:open:image', (e, url) => {
        $(document).trigger('lightbox:loading');
        content = $(document.createElement('img'));
        content.on('load', () => { $(document).trigger('lightbox:loaded') });
        $('#lightbox-content').html('');
        $('#lightbox-content').append(content);
        content.attr('src', url);
    });

    /**
     * Keyboard controls
     */
    $(document).keydown(function(e) {
        if (!$('#lightbox.active')) {
            return;
        }
        switch (e.which) {
            case 37: // left
                $(document).trigger('lightbox:previous');
                break;
            case 39: // right
                $(document).trigger('lightbox:next');
                break;
            case 27: // escape
                $(document).trigger('lightbox:hide');
                break;
        }
    });

    /**
     * Look for clicks on links with the target "lightbox"
     */
    $(document).on('click', 'a[target="lightbox"]', (e) => {
        // override lightbox behavior for ctrl clicks
        if (e.ctrlKey) {
            return;
        }
        if (content) {
            content.unbind('load');
        }
        let $a = $(e.currentTarget);
        // determine whether this item has a lightbox group parent
        if ($group = $a.closest('*[data-lightbox-group]')) {
            group = $group.find('a[target="lightbox"]');
            groupName = $group.attr('data-lightbox-group');
            groupSelector = '[data-lightbox-group="' + groupName + '"]';
        } else {
            group = $('body a[target="lightbox"]');
            groupName = 'body';
            groupSelector = 'body'
        }
        currentPos = group.index($a);
        nextPos = currentPos + 1;
        previousPos = currentPos - 1;
        if (nextPos > group.length - 1) {
            nextPos = 0;
        }
        if (previousPos < 0) {
            previousPos = group.length - 1;
        }
        // trigger event to open in lightbox
        let url = $a.attr('href');
        let type = $a.attr('data-lightbox-type') || 'auto';
        $a.trigger('lightbox:open:' + type, [url]);
        $a.trigger('lightbox:show');
        // prevent default
        e.preventDefault();
    });
});