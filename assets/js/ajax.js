($ =>
{
  'use strict';

  const number_format = (number, decimals = 2) =>
    parseFloat(number).toLocaleString(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    });

  const get_gain_perc = (result, original, decimals = 2) =>
    number_format(original == 0 ? 0 : 100 * ((result / original) - 1), decimals);

  /**
   * Get balance from exchange.
   * 
   * @param {(deposit_history: object, withdrawal_history: object, balance_exchange: object)} cb Triggered when succeeded.
   */
  const get_balance_summary = cb =>
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
    )
      .done((deposit_history, withdrawal_history, balance_exchange) =>
      {
        if (deposit_history[0].success && withdrawal_history[0].success && balance_exchange[0].success)
        {
          if (typeof cb === 'function') cb(deposit_history[0].data, withdrawal_history[0].data, balance_exchange[0].data);
        }
        else
        {
          // ERROR HANDLING !!
          if (typeof cb === 'function') cb(null, null, null);
        }
      })
      .fail(() =>
      {
        // ERROR HANDLING !!
        if (typeof cb === 'function') cb(null, null, null);
      });
  }

  /**
   * Update balance summary html.
   * 
   * @param {object} deposit_history 
   * @param {object} withdrawal_history 
   * @param {object} balance_exchange 
   */
  const echo_balance_summary = (deposit_history, withdrawal_history, balance_exchange) =>
  {
    /**
     * ERROR HANDLING !!
     */
    if (null == deposit_history || null == withdrawal_history || null == balance_exchange) return;

    let moneyflow_now = balance_exchange.amount_quote_total + withdrawal_history.total;

    $('.trader-total-deposited').text(number_format(deposit_history.total, 2));
    $('.trader-total-withdrawn').text(number_format(withdrawal_history.total, 2));
    $('.trader-current-balance').text(number_format(balance_exchange.amount_quote_total, 2));
    $('.trader-moneyflow').text(number_format(moneyflow_now, 2));
    $('.trader-total-gain-quote').text(number_format(moneyflow_now - deposit_history.total, 2));
    $('.trader-total-gain-perc').text(get_gain_perc(moneyflow_now, deposit_history.total, 2));
  }

  /**
   * Get portfolio balance.
   * 
   * @param {(balance: object)} cb Triggered when succeeded.
   */
  const get_portfolio_balance = cb =>
  {
    // SET LOADER !!

    $('button[value="do-portfolio-rebalance"]').prop('disabled', true);

    /**
     * Build the Configuration object to pass it as argument with the post request.
     */
    let config = {};
    $('form.trader-rebalance').serializeArray().forEach(obj => config[obj.name] = obj.value);

    /**
     * Get portfolio balance from server and trigger the callback function.
     */
    $.post({
      url: ajax_obj.ajax_url,
      data: {
        _ajax_nonce: ajax_obj.nonce,
        action: 'trader_get_balance',
        config: config,
      },
      success: balance =>
      {
        if (balance.success)
        {
          if (typeof cb === 'function') cb(balance.data);
        }
        else
        {
          // ERROR HANDLING !!
          if (typeof cb === 'function') cb(null);
        }
      },
      error: () =>
      {
        // ERROR HANDLING !!
        if (typeof cb === 'function') cb(null);
      }
    });
  }

  /**
   * Update html table with portfolio balance overview.
   * 
   * @param {object} balance 
   */
  const echo_portfolio_balance = balance =>
  {
    /**
     * ERROR HANDLING !!
     */
    if (null == balance) return;

    $('.trader-expected-fee').text(balance.expected_fee);

    let $tbody = $('table.trader-portfolio>tbody').empty();

    /**
     * Loop through the assets and rebuild the portfolio table.
     */
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

    /**
     * (Re-)enable the rebalance button(s).
     */
    $('button[value="do-portfolio-rebalance"]').prop('disabled', false);
  }

  /**
   * On document ready.
   */
  $(() =>
  {
    /**
     * Set elements.
     */
    this.table_portfolio = $('table.trader-portfolio');
    this.elem_deposited = $('.trader-total-deposited');
    this.elem_withdrawn = $('.trader-total-withdrawn');
    this.elem_cur_balance = $('.trader-current-balance');
    this.elem_moneyflow = $('.trader-moneyflow');
    this.elem_gain_quote = $('.trader-total-gain-quote');
    this.elem_gain_perc = $('.trader-total-gain-perc');

    /**
     * Determine whether these elements are printed on the current page.
     */
    let has_portfolio_table =
      0 < table_portfolio.length;
    let has_balance_fields = (
      0 < elem_deposited.length ||
      0 < elem_withdrawn.length ||
      0 < elem_cur_balance.length ||
      0 < elem_moneyflow.length ||
      0 < elem_gain_quote.length ||
      0 < elem_gain_perc.length);

    /**
     * Self repeating ticker to frequently update balance summary html.
     */
    const balance_ticker = () =>
    {
      get_balance_summary((deposit_history, withdrawal_history, balance_exchange) =>
      {
        echo_balance_summary(deposit_history, withdrawal_history, balance_exchange);

        setTimeout(balance_ticker, 5000);
      });
    }

    /**
     * Initial ajax trigger when all elements are printed on the current page.
     */
    if (has_portfolio_table && has_balance_fields)
    {
      // SET LOADER(S) ? !!

      /**
       * Get required data from server and call the html update functions.
       */
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
          action: 'trader_get_balance',
        }),
      )
        .done((deposit_history, withdrawal_history, balance) =>
        {
          if (deposit_history[0].success && withdrawal_history[0].success && balance[0].success)
          {
            echo_portfolio_balance(balance[0].data);

            echo_balance_summary(deposit_history[0].data, withdrawal_history[0].data, balance[0].data);
          }
        })
        .always(() =>
        {
          setTimeout(balance_ticker, 5000);
        });
    }

    /**
     * Initial ajax trigger when just the portfolio table is printed on the current page.
     */
    else if (has_portfolio_table)
    {
      get_portfolio_balance(echo_portfolio_balance);
    }

    /**
     * Initial ajax trigger when just one or more of the balance summary fields are printed on the current page.
     */
    else if (has_balance_fields)
    {
      balance_ticker();
    }

    /**
     * Handle rebalance form input events.
     */
    $('form.trader-rebalance').on('input', () =>
    {
      clearTimeout(this.rebalance_form_timer);

      this.rebalance_form_timer = setTimeout(() =>
      {
        get_portfolio_balance(echo_portfolio_balance);
      },
        1000);
    });
  });
})(jQuery);