const token      = document.querySelector('meta[name="dadata-api-key"]').content;
const fioUrl     = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/fio";
const addressUrl = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address";

function addSuggestToInput(inputEl, url) {
    inputEl.addEventListener('input', (e) => {
        let query   = inputEl.value;
        let options = {
            method: "POST",
            mode: "cors",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": "Token " + token
            },
            body: JSON.stringify({query: query})
        }
        
        fetch(url, options)
            .then(response => response.json())
            .then(result => {
                let data = result.suggestions;
                if (data.length == 0) {
                    return ;
                }

                if (document.querySelector('.dadata-suggestions-list')) {
                    document.querySelector('.dadata-suggestions-list').remove();
                }

                let list = document.createElement('ul');
                list.classList.add('dadata-suggestions-list');

                data.forEach((element) => {
                    let itemValue;
                    switch(inputEl.id) {
                        case 'user_data_firstName':
                        case 'inputName':
                            itemValue = element.data.name;
                            break;
                        case 'user_data_lastName':
                            itemValue = element.data.surname;
                            break;
                        case 'user_data_city':
                            itemValue = element.data.city;
                            break;
                    }
                    
                    if (itemValue !== null) {
                        let item = document.createElement('li');
                        item.setAttribute('data-value', itemValue);
                        item.appendChild(document.createTextNode(itemValue));
                        list.appendChild(item);
                    }
                });

                if (list.hasChildNodes()) {
                    let strList = list.outerHTML;
                    inputEl.insertAdjacentHTML('afterend', strList);

                    document.querySelector('.dadata-suggestions-list').addEventListener('click', (e) => {
                        if (e.target.tagName == 'LI') {
                            inputEl.value = e.target.getAttribute('data-value');
                            document.querySelector('.dadata-suggestions-list').remove();
                        }
                    });
                }
            })
            .catch(error => console.log("error", error));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    let addressElem = document.getElementById('user_data_city');
    if (addressElem) {
        addSuggestToInput(addressElem, addressUrl);
    }
    
    let nameElem = document.getElementById('user_data_firstName');
    if (nameElem) {
        addSuggestToInput(nameElem, fioUrl);
    }
    
    let surnameElem = document.getElementById('user_data_lastName');
    if (surnameElem) {
        addSuggestToInput(surnameElem, fioUrl);
    }
    
    let registerNameEl = document.getElementById('inputName');
    if (registerNameEl) {
        addSuggestToInput(registerNameEl, fioUrl);
    }
});
