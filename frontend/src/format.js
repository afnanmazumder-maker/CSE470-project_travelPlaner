export function money(amount) {
  return '$' + Number(amount).toLocaleString('en-US', { maximumFractionDigits: 2 })
}
