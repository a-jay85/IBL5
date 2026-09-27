/**
 * IBL5 player export for Google Sheets, authenticated with the X-API-Key header.
 *
 * Replaces this formula, which puts the raw key into the URL and therefore into
 * the web server's access log:
 *
 *   =IMPORTDATA("https://iblhoops.net/ibl5/api/v1/players/export?key=YOUR_KEY")
 *
 * Setup (once per spreadsheet):
 *   1. Extensions > Apps Script. Delete the default Code.gs body.
 *   2. Paste this whole file. Save.
 *   3. Run > setIblApiKey once from the editor. Approve the permission prompt.
 *      Replace "YOUR_KEY" below with the key from your IBL5 API Keys page first.
 *   4. In any cell: =IBL_PLAYERS()
 *
 * The key is kept in the script's private User Properties, never in a cell and
 * never in the sheet URL. The ?key= query form still works on the server; this
 * script simply stops using it.
 */

var IBL_EXPORT_URL = 'https://iblhoops.net/ibl5/api/v1/players/export';
var IBL_KEY_PROPERTY = 'IBL_API_KEY';

/**
 * Store the API key once. Edit the literal, run this function from the editor,
 * then change the literal back to "YOUR_KEY" so the key does not sit in source.
 */
function setIblApiKey() {
  var key = 'YOUR_KEY';
  if (key === 'YOUR_KEY' || key === '') {
    throw new Error('Edit setIblApiKey() and replace YOUR_KEY with your real key before running it.');
  }
  PropertiesService.getUserProperties().setProperty(IBL_KEY_PROPERTY, key);
}

/**
 * Custom function. Returns the full player export as a 2-D array.
 *
 * @param {string} refresh Optional. Pass any changing value (for example =NOW())
 *     to force Sheets to re-run the function; Sheets caches custom-function results.
 * @return {string[][]} Rows of the CSV export, header row first.
 * @customfunction
 */
function IBL_PLAYERS(refresh) {
  var key = PropertiesService.getUserProperties().getProperty(IBL_KEY_PROPERTY);
  if (!key) {
    throw new Error('No API key stored. Run setIblApiKey() from the Apps Script editor first.');
  }

  var response = UrlFetchApp.fetch(IBL_EXPORT_URL, {
    method: 'get',
    headers: { 'X-API-Key': key },
    muteHttpExceptions: true
  });

  var status = response.getResponseCode();
  if (status === 401) {
    throw new Error('IBL5 rejected the API key (HTTP 401). Re-run setIblApiKey() with a current key.');
  }
  if (status !== 200) {
    throw new Error('IBL5 export failed with HTTP ' + status + '. Try again later.');
  }

  var body = response.getContentText();
  if (body === '') {
    throw new Error('IBL5 export returned an empty body.');
  }
  return Utilities.parseCsv(body);
}
