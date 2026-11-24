// select2 4.1 dropdown positioning fixes, applied to every select2 field.
//
// 1. Scrolled parent: inside a Bootstrap modal the dropdown is attached to
//    the `.modal` element (see initSelect2 in app.js), which is position:
//    fixed and scrolls its own content. select2 positions the dropdown from
//    page offsets but never adds that element's scrollTop, so once the modal
//    is scrolled (e.g. down to "Assign To" in the tall Add Requirement form)
//    the list is drawn too high, by exactly the scroll distance.
//
// 2. Always below: select2 flips the list above the field when the window
//    has too little room underneath, which for fields near the bottom of a
//    form (Assign To) covers the fields above. It now always opens below,
//    like a native select's list in a form — the page or modal scrolls to
//    show the rest.
export function patchSelect2Positioning($) {
    const AttachBody = $.fn.select2?.amd?.require?.('select2/dropdown/attachBody');

    if (!AttachBody) {
        return;
    }

    AttachBody.prototype._positionDropdown = function () {
        const offset = this.$container.offset();
        const containerBottom = offset.top + this.$container.outerHeight(false);

        let $offsetParent = this.$dropdownParent;

        if ($offsetParent.css('position') === 'static') {
            $offsetParent = $offsetParent.offsetParent();
        }

        let parentOffset = { top: 0, left: 0 };
        let scroll = { top: 0, left: 0 };

        if ($offsetParent[0]?.isConnected) {
            parentOffset = $offsetParent.offset();

            // The document's own scroll is already part of offset(); only an
            // element that scrolls its own content (a modal) needs adding.
            if (!$offsetParent.is('html, body')) {
                scroll = { top: $offsetParent.scrollTop(), left: $offsetParent.scrollLeft() };
            }
        }

        this.$dropdown[0].classList.remove('select2-dropdown--above');
        this.$dropdown[0].classList.add('select2-dropdown--below');
        this.$container[0].classList.remove('select2-container--above');
        this.$container[0].classList.add('select2-container--below');

        this.$dropdownContainer.css({
            top: containerBottom - parentOffset.top + scroll.top,
            left: offset.left - parentOffset.left + scroll.left,
        });
    };
}
