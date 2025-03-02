/*global finnaCustomInit */
/*exported finna */
var finna = (function finnaModule() {

  var my = {
    init: function init() {
      // List of modules to be inited
      var modules = [
        'advSearch',
        'authority',
        'autocomplete',
        'contentFeed',
        'common',
        'changeHolds',
        'dateRangeVis',
        'feedback',
        'fines',
        'itemStatus',
        'layout',
        'menu',
        'myList',
        'openUrl',
        'primoAdvSearch',
        'record',
        'searchTabsRecommendations',
        'StreetSearch',
        'finnaSurvey',
        'multiSelect',
        'finnaMovement',
        'mdEditable',
        'a11y'
      ];

      $.each(modules, function initModule(ind, module) {
        if (typeof finna[module] !== 'undefined') {
          finna[module].init();
        }
      });
    }
  };

  return my;
})();

$(function onReady() {
  finna.init();

  // init custom.js for custom theme
  if (typeof finnaCustomInit !== 'undefined') {
    finnaCustomInit();
  }
});
