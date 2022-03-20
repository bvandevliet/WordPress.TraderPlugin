($ =>
{
  /**
   * Determine a html input element value is empty or its default.
   *
   * @param {Element|jQuery<HTMLInputElement>} input An html input element.
   */
  const is_empty_or_default = input =>
  {
    const $input = $(input);

    // eslint-disable-next-line eqeqeq
    return !$input.val() || $input.val() == $input.attr('default');
  };

  /**
   * Determine whether at least one sibling element exists in which all input elements have an empty or default value.
   *
   * @param {Element|JQuery<HTMLElement>} this_row
   */
  const empty_sibling_row_exists = this_row =>
  {
    const $this_row = $(this_row);

    return 0 < $this_row.siblings('.form-row-cloneable').filter((i, row) =>
      $(row).find('input:not(.cloneable-ignore)').toArray().every(is_empty_or_default)).length;
  };

  /**
   * On document ready.
   */
  $(() =>
  {
    /**
     * Cross-update input values that share the same "name" attribute value.
     */
    $('form input').on({
      input: e =>
      {
        const $this = $(e.target);

        $(`form input[name='${$this.attr('name')}']`).not(e.target)
          .filter((i, input) => !/\[\]/u.test($(input).attr('name'))).val($this.val());
      },
    });

    /**
     * Clones form rows with class ".form-row-cloneable" on user input.
     */
    $('.form-row-cloneable input').on({
      input: e =>
      {
        const $this = $(e.target);
        const $cloneable = $this.parents('.form-row-cloneable').first();

        if (
          !empty_sibling_row_exists($cloneable) && $this.val()
        && $cloneable.find('input:not(.cloneable-ignore)').toArray().every(input => $(input).val()))
        {
          const $clone = $cloneable.clone(true);

          $clone.find('input').each((i, input) =>
          {
            const $input = $(input);
            $input.val($input.attr('default'));
          });

          $cloneable.after($clone);
        }
      },
      blur: e =>
      {
        const $this = $(e.target);
        const $cloneable = $this.parents('.form-row-cloneable').first();

        if (
          empty_sibling_row_exists($cloneable) && $cloneable.siblings('.form-row-cloneable').length > 0
        && $cloneable.find('input:not(.cloneable-ignore)').toArray().every(is_empty_or_default))
        {
          $cloneable.remove();
        }
      },
    });
  });
})(jQuery);