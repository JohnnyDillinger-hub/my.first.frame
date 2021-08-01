document.querySelector('.sitemap-button').onclick = (e) => {

    e.preventDefault() // отмена действия по умолчанию

    createSitemap()

}

let links_counter = 0

function createSitemap()
{
    links_counter++;

    Ajax({data: {ajax:'sitemap', links_counter:links_counter}})
        .then((res) => {
            console.log('успех - ' + res)
        })
        .catch((res) => {
            console.log('ошибка - ' + res)
        });
}

createFile()

function createFile()
{

    /*
    * querySelectorAll() - возвращает статический (не динамический) NodeList,
    * содержащий все найденные элементы документа, которые соответствуют указанному селектору.
    * Пример селектора: input[type=file]
    * */
    let files = document.querySelectorAll('input[type=file]') // ханятся файлы, которые только хотим загрузить


    let fileStore = [] // хранятся файлы, которые уже загружены и отображаются на сайте

    // если массив files имеет длинну
    if(files.length)
    {

        // Метод forEach() выполняет указанную функцию один раз для каждого элемента в массиве
        /*
        * Пример:
        * array1 = ['a', 'b', 'c']
        *
        * array1.forEach(element => console.log(element));
        *
        * // expected output: 'a'
        * // expected output: 'b'
        * // expected output: 'c'
        *
        * */
        files.forEach(item => {

            item.onchange = function() {

                let multiple = false

                let parentContainer

                let container

                // если этот атрибут есть, то вернтся ture, если нет - false
                if(item.hasAttribute('multiple')){

                    multiple = true

                    /*
                    * Element.closest() возвращает ближайший родительский элемент (или сам элемент), который
                    * соответствует заданному CSS-селектору или null, если таковых элементов вообще нет
                    * */
                    parentContainer = this.closest('.gallery_container');

                    if(!parentContainer) return false

                    container = parentContainer.querySelectorAll('.empty_container')

                    if(container.length < this.files.length)
                    {

                        for(let i = 0; i < (this.files.length - container.length); i++)
                        {

                            let element = document.createElement('div')

                            // add class
                            element.classList.add('vg-dotted-square', 'vg-center', 'empty_container')

                            parentContainer.append(element)

                        }

                        container = parentContainer.querySelectorAll('.empty_container')

                    }
                }

                let fileName = item.name

                let attributeName = fileName.replace(/[\[\]]/g, '')

                for(let index in this.files)
                {
                    // hasOwnProperty() - проверяет, является ли указанное свойство,
                    // свойством данного объекта
                    if(this.files.hasOwnProperty(index))
                    {

                        if(multiple)
                        {

                            if(typeof fileStore[fileName] === 'undefined') fileStore[fileName] = []

                            let elementId = fileStore[fileName].push(this.files[index]) - 1

                            container[index].setAttribute(`data-deleteFileId-${attributeName}`, elementId)

                            showImage(this.files[index], container[index])

                            deleteNewFiles(elementId, fileName, attributeName, container[index])

                        }else{

                            container = this.closest('.img_container').querySelector('.img_show')

                            showImage(this.files[index], container)

                        }

                    }
                }
            }

            let area = item.closest('.img_wrapper')

            if(area)
            {

                dragAndDrop(area, item)

            }
        })

        let form = document.querySelector('#main-form')

        if(form)
        {

            /*
            * onsubmit -  возникает при отправке формы, это обычно происходит, когда пользователь нажимает специальную кнопку Submit
            * */
            form.onsubmit = function (e) {

                if(!isEmpty(fileStore))
                {

                    /*
                    * preventDefault () интерфейса Event сообщает User agent, что если событие не обрабатывается явно,
                    * его действие по умолчанию не должно выполняться так, как обычно
                    * */
                    e.preventDefault()

                    let formData = new FormData(this)

                    for(let i in fileStore)
                    {

                        if(fileStore.hasOwnProperty(i))
                        {

                            formData.delete(i)

                            let rowName = i.replace(/[\[\]]/g, '')

                            fileStore[i].forEach((item, index) => {

                                /*
                                * append() из интерфейса FormData добавляет новое значение в существующий ключ внутри
                                * объекта FormData, или создаёт ключ, в случае если он отсутствует
                                * */
                                formData.append(`${rowName}[${index}]`, item)

                            })

                        }

                    }

                formData.append('ajax', 'editData')

                    Ajax({
                        url:this.getAttribute('action'),
                        type: 'post',
                        data: formData,
                        processData: false,
                        contentType: false
                    }).then(res => {

                        try{

                            /*
                            * JSON.parse() - разбирает строку JSON, возможно с преобразованием получаемого в процессе разбора значения
                            * */
                            res = JSON.parse(res)

                            if(!res.success) throw new Error()

                            location.reload()

                        }catch (e){

                            alert('Произошла внтуренняя ошибка')

                        }

                    })

                }

            }

        }

        function deleteNewFiles(elementId, fileName, attributeName, container)
        {

            //addEventListener() регистрирует определённый обработчик события, вызванного на EventTarget
            container.addEventListener('click', function(){

                this.remove()

                delete fileStore[fileName][elementId]

            })

        }

        function showImage(item, container)
        {

            let reader = new FileReader()

            container.innerHTML = ''

            /*
            *  readAsDataURL используется для чтения содержимого указанного Blob или File.Когда операция закончится,
            * readyState (en-US) примет значение DONE, и будет вызвано событие loadend (en-US). В то же время, атрибут
            *   result (en-US) будет содержать данные как URL, представляющий файл, кодированый в base64 строку.
            * */
            reader.readAsDataURL(item)

            reader.onload = e => {

                container.innerHTML = '<img class="img_item" src="">'

                container.querySelector('img').setAttribute('src', e.target.result)

                container.classList.remove('empty_container')

            }

        }

        function dragAndDrop(area, input)
        {
            /*
            * dragenter - событие, когда мы перетаскиваем файл в выделенную зову
            * dragover  - событие, когда наш файл двигается в выделенной зоне
            * dragleave - событие, когда наш файл покидает выделенную зону
            * drop      - событие, когда мы отпускам курсором мыши наш файл, который мы таскали
            * */
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName, index) => {

                area.addEventListener(eventName, e => {

                    e.preventDefault()
                    e.stopPropagation()

                    // когда мы наводим файл на выделенную зону, то меняем её цвет
                    if(index < 2)
                    {

                        area.style.background = 'lightblue'

                    // и если убираем файл из области выделенной зоны, то возвращаем цвет по дефоту
                    }else{

                        area.style.background = '#fff'

                        if(index === 3)
                        {

                            /*
                            * Объект DataTransfer используется для хранения данных, перетаскиваемых мышью во время операции drag
                            * and drop. Он может хранить от одного до нескольких элементов данных, вне зависимости от их типа.
                            * */
                            input.files = e.dataTransfer.files

                            /*
                            * Отправляет событие в общую систему событий. Это событие подчиняется тем же правилам поведения
                            * "Захвата" и "Всплывания" как и непосредственно инициированные события.
                            *
                            * В данном случае делаем триггер события с 57-ой строчки данного скрипта
                            * files.forEach(item => {

                            *   item.onchange = function() { <------ данное событие
                            *
                            *       let multiple = false
                            *
                            *       let parentContainer
                            *
                            *       let container
                            * ...
                            * */
                            input.dispatchEvent(new Event('change'))

                        }
                    }

                })

            })

        }

    }
}

changeMenuPosition()

function changeMenuPosition()
{

    let form = document.querySelector('#main-form')

    if(form)
    {
        let selectParent = form.querySelector('select[name=parent_id]')

        let selectPosition = form.querySelector('select[name=menu_position]')

        if(selectParent && selectPosition)
        {

            // получаем дефолтное значение для parent_id
            let defaultParent = selectParent.value

            // получаем дефолтное значение для menu_position
            // +selectPosition.value - приводим полученное значение к числу
            let defaultPosition = +selectPosition.value

            selectParent.addEventListener('change', function (){

                // выбор по умолчанию
                let defaultChoose = false

                if(this.value === defaultParent) defaultChoose = true

                Ajax({
                    data: {
                        // получаем то, что хранится в input[name=table]
                        table:form.querySelector('input[name=table]').value,
                        'parent_id': this.value,
                        ajax: 'change_parent',
                        iteration: form.querySelector('#tableId') ? 1 : +!defaultChoose
                    }
                }).then(res => {

                    res = +res;

                    if(!res) return errorAlert()

                    let newSelect = document.createElement('select')

                    newSelect.setAttribute('name', 'menu_position')
                    newSelect.classList.add('vg_input', 'vg_text', 'vg_full', 'vg_firm_color1')

                    for(let i = 1; i <= res; i++)
                    {

                        let selected = defaultChoose && i === defaultPosition ? 'selected' : ''

                        newSelect.insertAdjacentHTML('beforeend', `<option ${selected} value="${i}">${i}</option>`)

                    }

                    // вставляем ДО newSelect
                    selectPosition.before(newSelect)

                    selectPosition.remove()

                    selectPosition = newSelect

                })

            })
        }
    }
}

blockParameters()

function blockParameters()
{

let wraps = document.querySelectorAll('.select_wrap')

    if(wraps.length)
    {

        let selectAllIndexes = []

        wraps.forEach(item => {

            // сохраняем следующий html-блок, идущий после блока wraps = document.querySelectorAll('.selector_wrap')
            let nextBlock = item.nextElementSibling

            // если nextBlock существует и в нем есть класс 'option_wrap'
            if(nextBlock && nextBlock.classList.contains('option_wrap'))
            {

                item.addEventListener('click', e => {

                    // если он НЕ содержит класс 'select_all'
                    if(!e.target.classList.contains('select_all'))
                    {

                        // то пишем аккордеон
                        nextBlock.slideToggle()

                    }else{

                        let index = [...document.querySelectorAll('.select_all')].indexOf(e.target)

                        if(typeof selectAllIndexes[index] === 'undefined') selectAllIndexes[index] = false

                        selectAllIndexes[index] = !selectAllIndexes[index]

                        nextBlock.querySelectorAll('input[type=checkbox]').
                        forEach(element => element.checked = selectAllIndexes[index]);

                    }

                })

            }

        })

    }

}

showHideMenuSearch()

function showHideMenuSearch()
{

    // обрабатываем событие "click" по полю с id = 'hideButton'
    document.querySelector('#hideButton').addEventListener('click', () => {

        // изменяем наличие у главного поля, имеющего класс 'vg-carcass', наличие класса 'vg-hide'
        // что позволяет равёрнуто отображать меню
        document.querySelector('.vg-carcass').classList.toggle('vg-hide')

    })

    let searchBtn = document.querySelector('#searchButton')

    let searchInput = searchBtn.querySelector('input[type=text]')

    // вызываем событие 'click'
    searchBtn.addEventListener('click', () => {

        // добавляем класс 'vg-search-reverse', чтобы отображать окно поиска
        searchBtn.classList.add('vg-search-reverse')

        // выставляем курсор в этой области (задаем фокус в указанной области)
        searchInput.focus()

    })

    // Событие 'blur' вызывается когда элемент теряет фокус (снимаем фокус с указанной области)
    searchInput.addEventListener('blur', () => {

        searchBtn.classList.remove('vg-search-reverse')

    })

}

let searchResultHover = (() => {

    let searchRes = document.querySelector('.search_res')

    let searchInput = document.querySelector('#searchButton input[type=text]')

    let defaultInputValue = null

    function searchKeyDown(e)
    {

        // если у объекта, который вызвал событие, нет id = 'vg-search-reverse' или не нажата кнопка "Вниз"/"Вверх"
        if(!document.querySelector('#searchButton').classList.contains('vg-search-reverse') ||
            (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;

        // сохраняем массив результатов полученных при поиске
        let children = [...searchRes.children]

        if(children.length)
        {

            let activeItem = searchRes.querySelector('.search_act')

            // indexOf() - используется для поиска значений в массиве
            let activeIndex = activeItem ? children.indexOf(activeItem) : -1

            if(e.key === 'ArrowUp')
            {
                activeIndex = activeIndex <= 0 ? children.length - 1 : --activeIndex
            }else{
                activeIndex = activeIndex === children.length - 1 ? 0 : ++activeIndex
            }

            children.forEach(item => item.classList.remove('search_act'))

            children[activeIndex].classList.add('search_act')

            searchInput.value = children[activeIndex].innerText

        }

    }

    // устанавливаем дефолтное значение в окне поиска
    function setDefaultValue()
    {

        searchInput.value = defaultInputValue

    }

    // Событие 'mouseleave' срабатывает, когда курсор манипулятора перемещается за границы элемента
    // Если курсор покидает область элемента, вызваем функцию setDefaultValue()
    searchRes.addEventListener('mouseleave', setDefaultValue)

    // Событие 'keydown' срабатывает, когда клавиша была нажата
    // После нажатия клавиши, вызываем функцию searchKeyDown(е)
    window.addEventListener('keydown', searchKeyDown)

    return () => {

        defaultInputValue = searchInput.value

        // в .children будут хранится объкты из выпадающего списка
        if(searchRes.children.length)
        {

            // сохраняем МАССИВ объектов из выпадающего списка
            let children = [...searchRes.children]

            // проходим по каждому элементу массива
            children.forEach(item => {

                // обрабатываем на каждом объекте массива событие 'mouseover'
                item.addEventListener('mouseover', () => {

                    //
                    children.forEach(el => el.classList.remove('search_act'))

                    item.classList.add('search_act')

                    searchInput.value = item.innerText

                })
            })

        }

    }

})



