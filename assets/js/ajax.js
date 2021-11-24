($ =>
{
  const number_format = (number, decimals = 2) =>
    parseFloat(number).toLocaleString(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    });

  const get_gain_perc = (result, original, decimals = 2) =>
    number_format(original == 0 ? 0 : 100 * ((result / original) - 1), decimals);

  /**
   * Update html with basic balance values.
   * 
   * @param {()} cb Triggered when succeeded.
   */
  window.update_balance_summary = cb =>
  {
    // SET LOADER ? !!

    $.when(
      $.post(
        ajax_obj.ajax_url, {
        _ajax_nonce: ajax_obj.nonce,
        action: 'trader_get_deposit_history',
      }),
      $.post(
        ajax_obj.ajax_url, {
        _ajax_nonce: ajax_obj.nonce,
        action: 'trader_get_withdrawal_history',
      }),
      $.post(
        ajax_obj.ajax_url, {
        _ajax_nonce: ajax_obj.nonce,
        action: 'trader_get_balance_exchange',
      }),
    ).done((deposit_history, withdrawal_history, balance_exchange) =>
    {
      if (deposit_history[0].success && withdrawal_history[0].success && balance_exchange[0].success)
      {
        deposit_history = deposit_history[0].data;
        withdrawal_history = withdrawal_history[0].data;
        balance_exchange = balance_exchange[0].data;

        let moneyflow_now = balance_exchange.amount_quote_total + withdrawal_history.total;

        $('.trader-total-deposited').text(number_format(deposit_history.total, 2));
        $('.trader-total-withdrawn').text(number_format(withdrawal_history.total, 2));
        $('.trader-current-balance').text(number_format(balance_exchange.amount_quote_total, 2));
        $('.trader-moneyflow').text(number_format(moneyflow_now, 2));
        $('.trader-total-gain-quote').text(number_format(moneyflow_now - deposit_history.total, 2));
        $('.trader-total-gain-perc').text(get_gain_perc(moneyflow_now, deposit_history.total, 2));
      }
      else
      {
        /**
         * MAKE SURE TO PROPERLY RETURN USER READABLE OUTPUT SOMEHOW !!
         */
        console.log({
          deposit_history: deposit_history[0].data,
          withdrawal_history: withdrawal_history[0].data,
          balance_exchange: balance_exchange[0].data
        });
      }

      if (typeof cb === 'function') cb();
    });
  }

  /**
   * Update html table with portfolio balance overview.
   */
  window.update_portfolio = () =>
  {
    // SET LOADER !!

    $('button[value="do-portfolio-rebalance"]').prop('disabled', true);

    /**
     * Build the Configuration object to pass it as argument with the post request.
     */
    let config = {};
    $('form.trader-rebalance').serializeArray().forEach(obj => config[obj.name] = obj.value);

    /**
     * Get portfolio balance from server.
     */
    $.post({
      url: ajax_obj.ajax_url,
      data: {
        _ajax_nonce: ajax_obj.nonce,
        action: 'trader_get_balance',
        config: config,
      },

      /**
       * Re-construct html portfolio table.
       */
      success: balance =>
      {
        if (balance.success)
        {
          balance = balance.data;

          $('.trader-expected-fee').text(balance.expected_fee);

          let $tbody = $('table.trader-portfolio>tbody').empty();

          balance.assets.forEach(asset =>
          {
            let $tr = $('<tr/>');

            let allocation_default = asset.allocation_rebl[Object.keys(asset.allocation_rebl)[0]] ?? 0;

            let allocation_current = 100 * asset.allocation_current;
            let allocation_rebl = 100 * allocation_default;
            let diff = allocation_current - allocation_rebl;

            $tr
              .append($('<td/>').text(asset.symbol));

            $tr
              .append($('<td class="trader-number"/>'))
              .append($('<td class="trader-number"/>').text('€'))
              .append($('<td class="trader-number"/>').text(number_format(asset.amount_quote, 2)))
              .append($('<td class="trader-number"/>').text(number_format(allocation_current, 2)))
              .append($('<td class="trader-number"/>').text('%'));

            $tr
              .append($('<td class="trader-number"/>'))
              .append($('<td class="trader-number"/>').text('€'))
              .append($('<td class="trader-number"/>').text(number_format(allocation_default * balance.amount_quote_total, 2)))
              .append($('<td class="trader-number"/>').text(number_format(allocation_rebl, 2)))
              .append($('<td class="trader-number"/>').text('%'));

            $tr
              .append($('<td class="trader-number"/>'))
              .append($('<td class="trader-number"/>').text((diff >= 0 ? '+' : '-') + number_format(Math.abs(diff), 2)))
              .append($('<td class="trader-number"/>').text('%'));

            $tbody.append($tr);
          });

          $('button[value="do-portfolio-rebalance"]').prop('disabled', false);
        }
      }
    });
  }

  /**
   * Handle DOM events.
   */
  $(() =>
  {
    $('form.trader-rebalance').on('change', () =>
    {
      update_portfolio();
    });
  });
})(jQuery);