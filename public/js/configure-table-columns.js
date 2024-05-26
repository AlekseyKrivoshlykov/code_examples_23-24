document.addEventListener('DOMContentLoaded', function () {
    let configureBtn = document.getElementById('configure-table-btn');
    configureBtn.addEventListener('click', function (e) {
        e.preventDefault();
        
        let target = e.currentTarget;
        let url    = target.href;
        fetch(url, {
            method: 'POST',
        })
            .then(resp => resp.json())
            .then(data => {
                let formEl = makeForm(data);
                let bodyEl = document.querySelector('body');
                bodyEl.insertAdjacentHTML('beforeend', formEl);
                handleModal();
                openModal();
            })
            .catch(error => {
                console.log('error', error);
            })
    });
});

function makeForm(data) {
    let object = data.fields;
    let entity = data.entityName;
    let html = '';

    html += '<div class="popup" id="modal-configure-table-columns" data-modal="configure-table">';
    html += '<form id="form-configure-table" class="popup__item" action="#" method="POST">';
    html += '<div class="popup__close js-modal-close"></div>';
    html += '<div id="form-content-wrapp">';
    html += '<div class="popup__title">Настроить таблицу</div>';
    html += '<div class="popup__content">';
    //rows
    for (prop in object) {
        html += '<div class="popup_inputholder popup__row popup__block">';
        html += `<input class="form__checkbox" name="${prop}" type="hidden" value="false">`
        html += `<input class="form__checkbox" id="${prop}" name="${prop}" type="checkbox" value="true">`;
        html += `<label for="${prop}">${object[prop]}</label>`;
        html += '<i id="element-move-up" class="mr-1 fas fa-solid fa-arrow-up"></i>';
        html += '<i id="element-move-down" class="fas fa-solid fa-arrow-down"></i>';
        html += '</div>'; 
    }
    html += '</div>'; //div content
    html += `<input type="hidden" name="entity-name" value=${entity}>`;
    html += '<button type="submit" class="btn">Сохранить</button>';
    html += '</div>'; //div wrapp
    html += '</form>';
    html += '<div class="popup__overlay"></div>';
    html += '</div>'; //div popup
    form.classList.remove('popup--active');
}

function openModal() {
    let form = document.getElementById('modal-configure-table-columns');
    closeModal();
    form.classList.add('popup--active');
}

function closeModal() {
    let form = document.getElementById('modal-configure-table-columns');
    form.classList.remove('popup--active');
}

// все обработчики для формы настроек
function handleModal() {
    document.querySelector('.js-modal-close').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
    });

    // обработчик для кнопки "вверх"
    let arrowsUp = document.querySelectorAll('#element-move-up');
    arrowsUp.forEach(function(arrowUp) {
        arrowUp.addEventListener('click', function (e) {
            let currentNode = e.target.parentElement;
            let prevNode    = currentNode.previousSibling;

            if (prevNode) {
                prevNode.insertAdjacentElement('beforebegin', currentNode);
            }
        });
    });

    // обработчик для кнопки "вниз"
    let arrowsDown = document.querySelectorAll('#element-move-down');
    arrowsDown.forEach(function(arrowDown) {
        arrowDown.addEventListener('click', function (e) {
            let currentNode = e.target.parentElement;
            let nextNode    = currentNode.nextSibling;

            if (nextNode) {
                nextNode.insertAdjacentElement('afterend', currentNode);
            }
        });
    });

    let form = document.getElementById('form-configure-table');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        
        let url = form.getAttribute('action');
        let formData = new FormData(form);
        let contentEl = document.getElementById('form-content-wrapp');
        let messageEl = document.createElement('div');

        fetch(url, {
            method: 'POST',
            body: formData,
        })
            .then(resp => resp.json())
            .then(data => {
                contentEl.style.display = 'none';
                messageEl.innerHTML = data.message;
                form.insertAdjacentElement('afterbegin', messageEl);
            })
            .catch(error => {
                contentEl.style.display = 'none';
                messageEl.innerHTML = 'Возникла ошибка, попробуйте сохранить настройки заново';
                form.insertAdjacentElement('afterbegin', messageEl);
            })
    });
}
