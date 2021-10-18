($ =>
{
  const is_empty_or_default = input =>
  {
    let $input = $(input);

    return !$input.val() || $input.val() == $input.attr('default');
  }

  const empty_sibling_row_exists = this_row =>
  {
    let $this_row = $(this_row);

    return $this_row.siblings('.form-row-cloneable').filter((i, row) =>
      $(row).find('input:not(.cloneable-ignore)').toArray().every(is_empty_or_default)).length
  }

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
        let $clone = $cloneable.clone(true);

        $clone.find('input').each((i, input) =>
        {
          let $input = $(input);
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
    }
  });

})(jQuery);