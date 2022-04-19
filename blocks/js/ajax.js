($ =>
{
  let
    /**
     * @type JQuery<HTMLTableElement>
     */
    $table_portfolio,
    /**
     * @type JQuery<HTMLElement>
     */
    $elem_deposited,
    /**
     * @type JQuery<HTMLElement>
     */
    $elem_withdrawn,
    /**
     * @type JQuery<HTMLElement>
     */
    $elem_cur_balance,
    /**
     * @type JQuery<HTMLElement>
     */
    $elem_moneyflow,
    /**
     * @type JQuery<HTMLElement>
     */
    $elem_gain_quote,
    /**
     * @type JQuery<HTMLElement>
     */
    $elem_gain_perc;

  /**
   * Configuration object.
   */
  let config = {};

  /**
   * Get balance from exchange.
   *
   * @param {(deposit_history: object, withdrawal_history: object, balance_exchange: object)} cb Triggered when succeeded.
   */
  const get_balance_summary = cb =>
  {
    /**
     * Rebuild the Configuration object to allow reading up-to-date parameter values.
     */
    config = {};
    $('form.trader-rebalance').serializeArray().forEach(obj => config[obj.name] = obj.value);

    $.when(
      $.post(
        ajax_obj.ajax_url, {
          _ajax_nonce: ajax_obj.nonce,
          action: 'trader_get_deposit_history',
        },
      ),
      $.post(
        ajax_obj.ajax_url, {
          _ajax_nonce: ajax_obj.nonce,
          action: 'trader_get_withdrawal_history',
        },
      ),
      $.post(
        ajax_obj.ajax_url, {
          _ajax_nonce: ajax_obj.nonce,
          action: 'trader_get_balance_exchange',
        },
      ),
    )
      .done((deposit_history, withdrawal_history, balance_exchange) =>
      {
        if (deposit_history[0]?.success && withdrawal_history[0]?.success && balance_exchange[0]?.success)
        {
          if (typeof cb === 'function') cb(deposit_history[0].data, withdrawal_history[0].data, balance_exchange[0].data);
        }
        else
        {
          console.error({
            deposit_history: deposit_history,
            withdrawal_history: withdrawal_history,
            balance_exchange: balance_exchange,
          });

          if (typeof cb === 'function') cb(null, null, null);
        }
      })
      .fail((deposit_history_xhr, withdrawal_history_xhr, balance_exchange_xhr) =>
      {
        console.error({
          deposit_history_xhr: deposit_history_xhr,
          withdrawal_history_xhr: withdrawal_history_xhr,
          balance_exchange_xhr: balance_exchange_xhr,
        });

        if (typeof cb === 'function') cb(null, null, null);
      });
  };

  /**
   * Update balance summary html.
   *
   * @param {object} deposit_history
   * @param {object} withdrawal_history
   * @param {object} balance_exchange
   */
  const echo_balance_summary = (deposit_history, withdrawal_history, balance_exchange) =>
  {
    // ERROR HANDLING ??
    if (deposit_history == null || withdrawal_history == null || balance_exchange == null) return;

    const moneyflow_now = balance_exchange.amount_quote_total + withdrawal_history.total;

    $('.trader-total-deposited')
      .text(number_format(deposit_history.total, 2));
    $('.trader-total-withdrawn')
      .text(number_format(withdrawal_history.total, 2));
    $('.trader-current-balance')
      .text(number_format(balance_exchange.amount_quote_total, 2));
    $('.trader-moneyflow')
      .text(number_format(moneyflow_now, 2));
    $('.trader-total-gain-quote')
      .text(number_format(moneyflow_now - deposit_history.total, 2));
    $('.trader-total-gain-perc')
      .text(get_gain_perc(moneyflow_now, deposit_history.total, 2));
    $('.trader-threshold-absolute')
      .text(number_format(config.rebalance_threshold / 100 * balance_exchange.amount_quote_total, 2));

    /**
     * De-activate loaders.
     */
    $elem_deposited
      .add($elem_withdrawn)
      .add($elem_cur_balance)
      .add($elem_moneyflow)
      .add($elem_gain_quote)
      .add($elem_gain_perc)
      .removeClass('loading');
  };

  /**
   * Get portfolio balance.
   *
   * @param {(balance: object)} cb Triggered when succeeded.
   */
  const get_portfolio_balance = cb =>
  {
    /**
     * Disable the rebalance button(s) and activate loaders.
     */
    $('button[value="do-portfolio-rebalance"]').prop('disabled', true);
    $table_portfolio.parent().addClass('loading');

    /**
     * Build the Configuration object to pass it as argument with the post request.
     */
    config = {};
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
          console.error(balance);

          if (typeof cb === 'function') cb(null);
        }
      },
      error: (balance_xhr) =>
      {
        console.error(balance_xhr);

        if (typeof cb === 'function') cb(null);
      },
    });
  };

  /**
   * Update html table with portfolio balance overview.
   *
   * @param {object} balance
   */
  const echo_portfolio_balance = balance =>
  {
    // ERROR HANDLING ??
    if (balance == null) return;

    $('.trader-expected-fee').text(balance.expected_fee);

    const $tbody_old = $('table.trader-portfolio>tbody');
    const $tbody_new = $('<tbody/>');

    /**
     * Loop through the assets and rebuild the portfolio table.
     */
    balance.assets.forEach(asset =>
    {
      const $tr = $('<tr/>');

      const allocation_default = asset.allocation_rebl[Object.keys(asset.allocation_rebl)[0]] ?? 0;

      const amount_balanced = allocation_default * balance.amount_quote_total;
      const alloc_perc_current = 100 * asset.allocation_current;
      const alloc_perc_rebl = 100 * allocation_default;
      const diff = alloc_perc_current - alloc_perc_rebl;
      const diff_quote = asset.amount_quote - amount_balanced;

      $tr
        .append($('<td/>').text(asset.symbol));

      $tr
        .append($('<td class="trader-number trader-no-padd-right"/>').text(`${config.quote_currency} `))
        .append($('<td class="trader-number trader-no-padd-left"/>').text(number_format(asset.amount_quote, 2)))
        .append($('<td class="trader-number trader-no-padd-right"/>').text(number_format(alloc_perc_current, 2)))
        .append($('<td class="trader-number trader-no-padd-left"/>').text(' %'));

      $tr
        .append($('<td class="trader-number trader-no-padd-right"/>').text(`${config.quote_currency} `))
        .append($('<td class="trader-number trader-no-padd-left"/>').text(number_format(amount_balanced, 2)))
        .append($('<td class="trader-number trader-no-padd-right"/>').text(number_format(alloc_perc_rebl, 2)))
        .append($('<td class="trader-number trader-no-padd-left"/>').text(' %'));

      $tr
        .append($('<td class="trader-number trader-no-padd-right"/>').text(`${config.quote_currency} ${diff_quote >= 0 ? '+' : '-'}`))
        .append($('<td class="trader-number trader-no-padd-left"/>').text(number_format(Math.abs(diff_quote), 2)))
        .append($('<td class="trader-number trader-no-padd-right"/>').text((diff >= 0 ? '+' : '-') + number_format(Math.abs(diff), 2)))
        .append($('<td class="trader-number trader-no-padd-left"/>').text(' %'));

      $tbody_new.append($tr);
    });

    /**
     * Replace tbody at once to prevent flickering.
     */
    $tbody_old.replaceWith($tbody_new);

    /**
     * (Re-)enable the rebalance button(s) and de-activate loaders.
     */
    $table_portfolio.parent().removeClass('loading');
    $('button[value="do-portfolio-rebalance"]').prop('disabled', false);
  };

  /**
   * On document ready.
   */
  $(() =>
  {
    /**
     * Set elements.
     */
    $table_portfolio = $('table.trader-portfolio');
    $elem_deposited = $('.trader-total-deposited');
    $elem_withdrawn = $('.trader-total-withdrawn');
    $elem_cur_balance = $('.trader-current-balance');
    $elem_moneyflow = $('.trader-moneyflow');
    $elem_gain_quote = $('.trader-total-gain-quote');
    $elem_gain_perc = $('.trader-total-gain-perc');

    /**
     * Activate loaders.
     */
    $table_portfolio.parent()
      .add($elem_deposited)
      .add($elem_withdrawn)
      .add($elem_cur_balance)
      .add($elem_moneyflow)
      .add($elem_gain_quote)
      .add($elem_gain_perc)
      .addClass('loading');

    /**
     * Determine whether these elements are printed on the current page.
     */
    const has_portfolio_table =
      0 < $table_portfolio.length;
    const has_balance_fields = (
      0 < $elem_deposited.length ||
      0 < $elem_withdrawn.length ||
      0 < $elem_cur_balance.length ||
      0 < $elem_moneyflow.length ||
      0 < $elem_gain_quote.length ||
      0 < $elem_gain_perc.length);

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
    };

    /**
     * Initial ajax trigger when all elements are printed on the current page.
     */
    if (has_portfolio_table/* && has_balance_fields*/)
    {
      /**
       * Build the Configuration object to pass it as argument with the post request.
       */
      config = {};
      $('form.trader-rebalance').serializeArray().forEach(obj => config[obj.name] = obj.value);

      /**
       * Get required data from server and call the html update functions.
       */
      $.when(
        $.post(
          ajax_obj.ajax_url, {
            _ajax_nonce: ajax_obj.nonce,
            action: 'trader_get_deposit_history',
          },
        ),
        $.post(
          ajax_obj.ajax_url, {
            _ajax_nonce: ajax_obj.nonce,
            action: 'trader_get_withdrawal_history',
          },
        ),
        $.post(
          ajax_obj.ajax_url, {
            _ajax_nonce: ajax_obj.nonce,
            action: 'trader_get_balance',
            config: config,
          },
        ),
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
    // else if (has_portfolio_table)
    // {
    //   get_portfolio_balance(echo_portfolio_balance);
    // }

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
    $('form.trader-rebalance').on('input', e =>
    {
      /**
       * Ignore inputs that do not affect allocations.
       */
      const input_name = $(e.target).attr('name');
      if ([
        'automation_enabled',
        'interval_hours',
        'rebalance_threshold',
      ].some(name => name === input_name))
      {
        return;
      }

      clearTimeout(this.rebalance_form_timer);

      /**
       * Disable the rebalance button(s) and activate loaders.
       */
      $('button[value="do-portfolio-rebalance"]').prop('disabled', true);
      $table_portfolio.parent().addClass('loading');

      this.rebalance_form_timer = setTimeout(() =>
      {
        get_portfolio_balance(echo_portfolio_balance);
      },
      1000);
    });
  });
})(jQuery);