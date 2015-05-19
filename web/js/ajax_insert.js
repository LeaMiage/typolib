$('#locale_selector').on('change', function() {
    $.ajax({
        url: "api/",
        type: "GET",
        data: "action=codes&locale=" + this.value,
        dataType: "html",
        success: function(response) {
            $("#code_selector").html(response);
        },
        error: function() {
            console.log("AJAX failure - get codes");
        }
    });
});

$('#code_selector').on('change', function() {
    locale = $('#locale_selector').val();
    $.ajax({
        url: "api/",
        type: "GET",
        data: "action=rules&locale=" + locale + "&code=" + this.value,
        dataType: "html",
        success: function(response) {
            $("#results").html(response);
        },
        error: function() {
            console.log("AJAX failure - get rules");
        }
    });
});

$('#addrule_type').on('change', function() {
    rule_type = $('#addrule_type :selected').text();
    $('#rule').val(rule_type);
});

$('#submitRule').click(function(event) {
    event.preventDefault();
    code = $('#code_selector').val();
    locale = $('#locale_selector').val();
    rule_type = $('#addrule_type').val();
    rule = $('#rule').val();
    comment = $('#comment').val();
    placeholder = $('#addrule_type :selected').text();
    $('#rule').val(placeholder);
    $('#comment').val('');
    $.ajax({
        url: "api/",
        type: "GET",
        data: "action=adding_rule&locale=" + locale + "&code=" + code + "&type=" + rule_type + "&content=" + rule + "&comment=" + comment,
        dataType: "html",
        success: function(response) {
            $("#results").html(response);
        },
        error: function() {
            console.log("AJAX failure - add rule");
        }
    });
});

$('#submitRuleException').click(function(event) {
    event.preventDefault();
    code = $('#code_selector').val();
    locale = $('#locale_selector').val();
    exception = $('#exception').val();
    id_rule = $('#id_rule').val();
    $.ajax({
        url: "api/",
        type: "GET",
        data: "action=adding_exception&locale=" + locale + "&code=" + code + "&id_rule=" + id_rule + "&content=" + exception,
        dataType: "html",
        success: function(response) {
            $("#results").html(response);
        },
        error: function() {
            console.log("AJAX failure - add rule");
        }
    });
});
