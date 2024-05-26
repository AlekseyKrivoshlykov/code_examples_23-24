 // обработка формы опроса после загрузки чека
$('#ajax-form-survey-after-receipt').submit(function(e) {
    e.preventDefault();

    if (!validateSurveyAfterReceiptForm()) {
        return false;
    }

    var self       = this;
    var formData   = new FormData(self);
    var thanksNode = $('#modal-vote-second-step');
    var voteNode   = $('#modal-vote');

    $.ajax({
        type: 'POST',
        url: $(self).attr('action'),
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (response) {
            if (response.status == 'success') {
                voteNode.removeClass('popup--active');
                thanksNode.addClass('popup--active');
            } else if (response.status == 'error' && response.message == 'noAuth') {
                alert('Необходимо авторизоваться на сайте.');
            } else if (response.status == 'error' && response.message == 'noData') {
                alert('Пожалуйста, ответьте на вопросы.');
            }
        },
        error: function () {
            alert('Упс! Что-то пошло не так. Попробуйте снова')
        }
    });
});

function validateSurveyAfterReceiptForm() {
    $('.reason-answers-wrap').removeClass('survey-after-receipt_error');
    $('.source-answers-wrap').removeClass('survey-after-receipt_error');

    if ($('input[name="rating"]:checked').length == 0) {
        alert('Выберите рейтинг приза');
        return false;
    }

    if ($('input[name="reason[]"]:checked').length == 0) {
        $('.reason-answers-wrap').addClass('survey-after-receipt_error');
        alert('Выберите хотя бы один вариант ответа');
        return false;
    }

    if ($('input[name="source[]"]:checked').length == 0) {
        $('.source-answers-wrap').addClass('survey-after-receipt_error');
        alert('Выберите хотя бы один вариант ответа');
        return false;
    }

    return true;
}
