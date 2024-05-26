let confirmateBtns = document.querySelectorAll('.confirmate_email_btn');
let modalSuccess   = document.getElementById('modal-send_mail_success');
let modalError     = document.getElementById('modal-send_mail_fail');
let userMail       = document.getElementById('user_data_mail').innerText;

confirmateBtns.forEach(btn => {
    btn.addEventListener('click', function () {
        let data = new FormData();
        data.set('user_mail', userMail);

        fetch('#', {
            method: 'POST',
            body: data,
        })
            .then(resp => resp.json())
            .then(data => {
                if (data.status == 'success') {
                    modalSuccess.classList.add('popup--active');
                } else {
                    modalError.classList.add('popup--active');
                }
            })
            .catch(error => {
                console.log('error', error);
            }) 
    });
})
