$(document).on("submit", "#form_comment_new", function(e){
    e.preventDefault();
    return  false;
});

$(document).on('click', '#mySubmitButton', function(e){
    e.preventDefault();
    var form = $('#form_comment_new');
    // get the properties and values from the form
    var form_data = form.serialize();
    var values = {};
    $.each($("form").serializeArray(), function (i, field) {
        values[field.name] = field.value;
    });
    //var form_data_array = form.serializeArray();
    var getValue = function (valueName) {
        return values[valueName];
    };
    var comment = getValue('comment_form[comment]');
    var author = $('.js-user-info').data('fullname');
    var time = 'a few moments ago';

    // always makes sense to signal user that something is happening
    $('#submit-icon').attr('class', 'fas fa-spinner fa-spin');
    $('#submit-text').text('Sending');

    // simple approach avoid submitting multiple times
    $('#mySubmitButton').attr("disabled",true);

    // the actual request to your newAction
    $.ajax({
        url: "{{ path('comment_new', {'object' : 'class','objectId': class.id}) }}",
        type: 'POST',
        dataType: 'json',
        data: form_data,
        success:function(data){

            // handling the response data from the controller
            if(data.status == 'Error'){
                console.log("[API] ERROR: "+data.message);
                var innerHTML = $(data.html);
                $('#form_comment_new').remove();
                $('#new-comment-form').html(innerHTML);
            }
            if(data.status == 'Success'){
                var innerHTML = $(data.html);
                $('#form_comment_new').remove();
                $('#new-comment-form').html(innerHTML);
                $('#comment_form_comment').val('');
                $('#comments-list').prepend( $('<li>\n' +
                    '                                    <div class="comment-text">\n' +
                    '                                        <p><strong><a href="#">'+author+'</a></strong></p>\n' +
                    '                                        <p>'+comment+'</p>\n' +
                    '                                        <span class="date sub-comment-text">'+time+'</span>\n' +
                    '                                    </div>\n' +
                    '                                </li>').hide().delay(500).show('slow') );

                console.log("[API] SUCCESS: "+data.message);
            }

            // signal to user the action is done
            $('#submit-icon').attr('class', 'far fa-comment');
            $('#submit-text').text('Send');
            $('#mySubmitButton').attr("disabled",false);
        }
    });
});