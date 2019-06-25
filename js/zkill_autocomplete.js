// Instantiate the Bloodhound suggestion engine
var zkill = new Bloodhound({
  datumTokenizer: function(datum) {
    return Bloodhound.tokenizers.whitespace(datum.name);
  },
  queryTokenizer: Bloodhound.tokenizers.whitespace,
  remote: {
    wildcard: '%QUERY',
    url: 'https://zkillboard.com/autocomplete/%QUERY/',
    transform: function(response) {
      // Map the remote source JSON array to a JavaScript object array
      return $.map(response, function(zkill) {
        if ( zkill.type == 'character' ) {
          return {
            name: zkill.name,
            id: zkill.id
          };
         }
      });
    }
  }
});

// Instantiate the Typeahead UI
$('.typeahead').typeahead(null, {
  display: 'name',
  limit: 20,
  source: zkill,
  templates: {
  suggestion: function(data) {
      return '<div class="tt-sug-div"><img class="tt-sug-img img-rounded" src="https://imageserver.eveonline.com/Character/'+data.id+'_32.jpg"><p class="tt-sug-text">'+data.name+'</p></div>';
    }
  }
}).bind('typeahead:render', function(e) {
    $('.typeahead').parent().find('.tt-selectable:first').addClass('tt-cursor');
}).bind('typeahead:select', function(ev, suggestion) {
    $('#inv-button').removeClass('disabled');
    $('#inv-id').val(suggestion.id);
}).on('keyup', function(e) {
    if(e.which != 13) {
      $('#inv-button').addClass('disabled');
      $('#inv-id').val('');
    }
});
