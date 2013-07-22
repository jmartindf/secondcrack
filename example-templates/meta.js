function updateMeta(event) {
  var tagArr = $("input:checked[name='tags']").map(function () { return this.value; });
  var catArr = $("input:checked[name='cats']").map(function () { return this.value; });
  var catStr = "Categories: "+catArr.get().join(",");
  var tagStr = "Tags: "+tagArr.get().join(",");
  $("#headers").val(catStr+"\n"+tagStr);
}

$(document).ready(function() {

  $.getJSON('/meta.json', function(data) {
    var items = [];

    $.each(data['cats'], function(key, val) {
      var input = $(document.createElement('input')).attr({
        id: 'cats_'+val
        ,name: 'cats'
        ,value: val
        ,type: 'checkbox'
      });

      var label = $(document.createElement('label')).attr({for: 'cats_'+val}).text(val);
      $('#cat_ul').append($(document.createElement('li')).append(input).append(label));
    }); // end each cats

    $.each(data['tags'], function(key, val) {
      var input = $(document.createElement('input')).attr({
        id: 'tags_'+val
        ,name: 'tags'
        ,value: val
        ,type: 'checkbox'
      });
      var label = $(document.createElement('label')).attr({for: 'tags_'+val}).text(val);
      $('#tag_ul').append($(document.createElement('li')).append(input).append(label));
    }); // end each tags

  }); // end JSON

  $("#meta").on('change',updateMeta);
});

