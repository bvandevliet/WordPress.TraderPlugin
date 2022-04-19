const number_format = (number, decimals = 2) =>
  parseFloat(number).toLocaleString(undefined, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });

const get_gain_perc = (result, original, decimals = 2) =>
  // eslint-disable-next-line eqeqeq
  number_format(original == 0 ? 0 : 100 * ((result / original) - 1), decimals);