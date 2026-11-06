@extends('legal.layout')

@section('content')
    <article class="legal">
        <header class="legal-head">
            <h1 class="legal-h1">Политика конфиденциальности</h1>
            <p class="legal-subtitle">Порядок обработки и защиты персональных данных на сайте Sites-shop</p>
            <p class="legal-edition">Редакция от 07.11.2026</p>
        </header>

        <nav class="legal-toc" aria-label="Содержание">
            <p class="legal-toc__title">Содержание</p>
            <ol class="legal-toc__list">
                <li><a href="#privacy-1" class="legal-toc__link">1. Общие положения</a></li>
                <li><a href="#privacy-2" class="legal-toc__link">2. Термины</a></li>
                <li><a href="#privacy-3" class="legal-toc__link">3. Какие данные обрабатываются</a></li>
                <li><a href="#privacy-4" class="legal-toc__link">4. Цели обработки</a></li>
                <li><a href="#privacy-5" class="legal-toc__link">5. Правовые основания</a></li>
                <li><a href="#privacy-6" class="legal-toc__link">6. Файлы cookie</a></li>
                <li><a href="#privacy-7" class="legal-toc__link">7. Передача третьим лицам</a></li>
                <li><a href="#privacy-8" class="legal-toc__link">8. Сроки хранения</a></li>
                <li><a href="#privacy-9" class="legal-toc__link">9. Права пользователя</a></li>
                <li><a href="#privacy-10" class="legal-toc__link">10. Безопасность данных</a></li>
                <li><a href="#privacy-11" class="legal-toc__link">11. Несовершеннолетние</a></li>
                <li><a href="#privacy-12" class="legal-toc__link">12. Изменения политики</a></li>
                <li><a href="#privacy-13" class="legal-toc__link">13. Контакты оператора</a></li>
            </ol>
        </nav>

        <section id="privacy-1" class="legal-section">
            <h2 class="legal-h2">1. Общие положения</h2>

            <p class="legal-clause">
                <span class="legal-clause__num">1.1.</span>
                Настоящая Политика конфиденциальности (далее — Политика) определяет порядок обработки и защиты
                персональных данных пользователей сайта <a href="https://sites-shop.ru" class="legal-link">https://sites-shop.ru</a>
                (далее — Сайт) и разработана в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ
                «О персональных данных».
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">1.2.</span>
                Оператор персональных данных (далее — Оператор): Ковалёв Вячеслав Юрьевич, ИНН 910618962001,
                плательщик налога на профессиональный доход (самозанятый), адрес электронной почты
                <a href="mailto:kovalev.v.u@gmail.com" class="legal-link">kovalev.v.u@gmail.com</a>.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">1.3.</span>
                Политика применяется ко всем персональным данным, которые Оператор получает через Сайт,
                включая данные, полученные при регистрации, заполнении профиля, размещении объявлений,
                обмене сообщениями, оплате подписки и обращении в поддержку.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">1.4.</span>
                Используя Сайт, регистрируясь на нём или оплачивая подписку, пользователь подтверждает,
                что ознакомился с Политикой и согласен с ней. Условия оказания платных услуг определяются
                <a href="{{ route('offer') }}" class="legal-link">публичной офертой</a>.
            </p>
        </section>

        <section id="privacy-2" class="legal-section">
            <h2 class="legal-h2">2. Термины</h2>

            <p class="legal-para"><strong class="legal-term">Персональные данные</strong> — любая информация,
                относящаяся прямо или косвенно к определённому или определяемому физическому лицу.</p>
            <p class="legal-para"><strong class="legal-term">Обработка персональных данных</strong> — любое действие
                с персональными данными, включая сбор, запись, хранение, уточнение, использование, передачу,
                блокирование, удаление и уничтожение.</p>
            <p class="legal-para"><strong class="legal-term">Пользователь</strong> — лицо, использующее Сайт,
                в том числе зарегистрированное в личном кабинете.</p>
            <p class="legal-para"><strong class="legal-term">Оператор</strong> — лицо, указанное в пункте 1.2 Политики,
                которое организует обработку персональных данных и определяет её цели и состав.</p>
        </section>

        <section id="privacy-3" class="legal-section">
            <h2 class="legal-h2">3. Какие данные обрабатываются</h2>

            <p class="legal-clause">
                <span class="legal-clause__num">3.1.</span>
                Данные аккаунта: имя (отображаемое имя), адрес электронной почты, аватар, город, описание профиля,
                идентификаторы VK ID и Яндекс ID, дата последнего входа через соответствующий сервис.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.2.</span>
                Данные объявлений: домен, название и описание сайта, цена, доход, категория, а также сведения
                и файлы, подтверждающие владение доменом.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.3.</span>
                Данные взаимодействия: сообщения в чатах между пользователями, отзывы, жалобы, избранное
                и сохранённые фильтры поиска.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.4.</span>
                Платёжные данные: сумма и период подписки, статус платежа, адрес электронной почты для чека,
                идентификатор платежа у платёжного сервиса. Реквизиты банковских карт Оператор не получает
                и не хранит — их обрабатывает платёжный сервис Робокасса.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.5.</span>
                Технические данные: IP-адрес, файлы cookie, сведения о браузере и устройстве, страницы и действия
                на Сайте, дата и время обращения, журналы сервера.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.6.</span>
                Данные, полученные при входе через VK ID или Яндекс ID, — в объёме, который передаёт
                соответствующий сервис: идентификатор пользователя, имя, аватар, адрес электронной почты
                (если он передан).
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.7.</span>
                Оператор не обрабатывает специальные категории персональных данных и биометрические данные.
            </p>
            <p class="legal-clause">
                <span class="legal-clause__num">3.8.</span>
                Имя, аватар, город, рейтинг, отзывы и объявления пользователя доступны другим посетителям Сайта.
                Не указывайте в описаниях и сообщениях данные, которые не хотите раскрывать.
            </p>
        </section>

        <section id="privacy-4" class="legal-section">
            <h2 class="legal-h2">4. Цели обработки</h2>

            <p class="legal-clause"><span class="legal-clause__num">4.1.</span> Регистрация, авторизация и предоставление доступа к личному кабинету.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.2.</span> Размещение объявлений, связь покупателя и продавца, обмен сообщениями, отзывы и рейтинги.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.3.</span> Оформление и оплата подписки, формирование фискальных чеков и учёт платежей.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.4.</span> Направление уведомлений о событиях на Сайте; от рассылок можно отказаться в настройках кабинета.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.5.</span> Модерация объявлений, проверка владения доменом, предотвращение мошенничества и злоупотреблений.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.6.</span> Ответы на обращения и поддержка пользователей.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.7.</span> Исполнение требований законодательства Российской Федерации, включая налоговый и бухгалтерский учёт.</p>
            <p class="legal-clause"><span class="legal-clause__num">4.8.</span> Обезличенная статистика и улучшение работы Сайта.</p>
        </section>

        <section id="privacy-5" class="legal-section">
            <h2 class="legal-h2">5. Правовые основания</h2>

            <p class="legal-clause"><span class="legal-clause__num">5.1.</span> Согласие пользователя на обработку персональных данных (пункт 1 части 1 статьи 6 Федерального закона № 152-ФЗ).</p>
            <p class="legal-clause"><span class="legal-clause__num">5.2.</span> Исполнение договора, стороной которого является пользователь, — для оказания услуг и приёма оплаты.</p>
            <p class="legal-clause"><span class="legal-clause__num">5.3.</span> Законные интересы Оператора — обеспечение безопасности Сайта и борьба со злоупотреблениями.</p>
            <p class="legal-clause"><span class="legal-clause__num">5.4.</span> Требования законодательства — для хранения платёжных документов и налоговой отчётности.</p>
            <p class="legal-clause"><span class="legal-clause__num">5.5.</span> Согласие можно отозвать в любое время, направив обращение на адрес из раздела 13. Отзыв согласия может сделать невозможным использование Сайта и личного кабинета.</p>
        </section>

        <section id="privacy-6" class="legal-section">
            <h2 class="legal-h2">6. Файлы cookie</h2>

            <p class="legal-clause"><span class="legal-clause__num">6.1.</span> Сайт использует cookie для поддержания сессии, авторизации, сохранения настроек и защиты от злоупотреблений.</p>
            <p class="legal-clause"><span class="legal-clause__num">6.2.</span> Пользователь может отключить cookie в настройках браузера, однако часть функций Сайта станет недоступна.</p>
            <p class="legal-clause"><span class="legal-clause__num">6.3.</span> Сторонние аналитические сервисы на Сайте не подключаются. Оператор может вести обезличенные журналы сервера для диагностики и защиты Сайта.</p>
        </section>

        <section id="privacy-7" class="legal-section">
            <h2 class="legal-h2">7. Передача третьим лицам</h2>

            <p class="legal-clause"><span class="legal-clause__num">7.1.</span> Робокасса — для приёма оплаты и формирования фискальных чеков.</p>
            <p class="legal-clause"><span class="legal-clause__num">7.2.</span> VK и Яндекс — при выборе входа через VK ID или Яндекс ID соответственно.</p>
            <p class="legal-clause"><span class="legal-clause__num">7.3.</span> Яндекс SmartCaptcha — для защиты форм от автоматических отправок; передаются IP-адрес, параметры браузера и токен проверки.</p>
            <p class="legal-clause"><span class="legal-clause__num">7.4.</span> Провайдер хостинга и инфраструктуры, а также сервис доставки электронной почты — для работы Сайта и отправки уведомлений.</p>
            <p class="legal-clause"><span class="legal-clause__num">7.5.</span> Государственные органы — по мотивированному запросу в случаях, предусмотренных законодательством.</p>
            <p class="legal-clause"><span class="legal-clause__num">7.6.</span> Оператор не продаёт персональные данные и не передаёт их третьим лицам для собственного маркетинга.</p>
            <p class="legal-clause"><span class="legal-clause__num">7.7.</span> Целенаправленная трансграничная передача персональных данных Оператором не осуществляется.</p>
        </section>

        <section id="privacy-8" class="legal-section">
            <h2 class="legal-h2">8. Сроки хранения</h2>

            <p class="legal-clause"><span class="legal-clause__num">8.1.</span> Данные аккаунта — до удаления аккаунта или отзыва согласия.</p>
            <p class="legal-clause"><span class="legal-clause__num">8.2.</span> Данные объявлений — до удаления объявления и 12 месяцев после этого для разбора возможных споров.</p>
            <p class="legal-clause"><span class="legal-clause__num">8.3.</span> Сообщения, отзывы и жалобы — 3 года с даты последнего взаимодействия.</p>
            <p class="legal-clause"><span class="legal-clause__num">8.4.</span> Платёжные документы и чеки — 5 лет в соответствии с требованиями налогового и бухгалтерского законодательства.</p>
            <p class="legal-clause"><span class="legal-clause__num">8.5.</span> Технические журналы — до 12 месяцев, если иной срок не установлен законом.</p>
            <p class="legal-clause"><span class="legal-clause__num">8.6.</span> По истечении сроков данные удаляются или обезличиваются.</p>
        </section>

        <section id="privacy-9" class="legal-section">
            <h2 class="legal-h2">9. Права пользователя</h2>

            <p class="legal-clause"><span class="legal-clause__num">9.1.</span> Получать информацию об обработке своих персональных данных и копию обрабатываемых данных.</p>
            <p class="legal-clause"><span class="legal-clause__num">9.2.</span> Требовать уточнения, блокирования или уничтожения данных, если они неполны, устарели, неточны или обрабатываются незаконно.</p>
            <p class="legal-clause"><span class="legal-clause__num">9.3.</span> Отозвать согласие на обработку персональных данных.</p>
            <p class="legal-clause"><span class="legal-clause__num">9.4.</span> Обратиться в уполномоченный орган по защите прав субъектов персональных данных или в суд.</p>
            <p class="legal-clause"><span class="legal-clause__num">9.5.</span> Запрос направляется на адрес <a href="mailto:kovalev.v.u@gmail.com" class="legal-link">kovalev.v.u@gmail.com</a>. Оператор отвечает в течение 10 рабочих дней с даты получения запроса.</p>
            <p class="legal-clause"><span class="legal-clause__num">9.6.</span> Для удаления аккаунта достаточно направить обращение с адреса электронной почты, указанного в профиле. Данные, хранение которых обязательно по закону, сохраняются до истечения установленного срока.</p>
        </section>

        <section id="privacy-10" class="legal-section">
            <h2 class="legal-h2">10. Безопасность данных</h2>

            <p class="legal-clause"><span class="legal-clause__num">10.1.</span> Оператор принимает организационные и технические меры защиты: передача данных по HTTPS, ограничение доступа, резервное копирование и журналирование действий.</p>
            <p class="legal-clause"><span class="legal-clause__num">10.2.</span> Доступ к персональным данным предоставляется только уполномоченным лицам в объёме, необходимом для выполнения их задач.</p>
            <p class="legal-clause"><span class="legal-clause__num">10.3.</span> При выявлении инцидента с персональными данными Оператор уведомляет уполномоченный орган в сроки, установленные законодательством.</p>
        </section>

        <section id="privacy-11" class="legal-section">
            <h2 class="legal-h2">11. Несовершеннолетние</h2>

            <p class="legal-clause"><span class="legal-clause__num">11.1.</span> Сайт не предназначен для лиц младше 18 лет. Оператор сознательно не собирает данные несовершеннолетних. Если такие данные станут известны, они будут удалены.</p>
        </section>

        <section id="privacy-12" class="legal-section">
            <h2 class="legal-h2">12. Изменения политики</h2>

            <p class="legal-clause"><span class="legal-clause__num">12.1.</span> Оператор вправе изменять Политику. Новая редакция публикуется на этой странице с указанием даты редакции.</p>
            <p class="legal-clause"><span class="legal-clause__num">12.2.</span> Продолжение использования Сайта после публикации новой редакции означает согласие с изменениями.</p>
        </section>

        <section id="privacy-13" class="legal-section">
            <h2 class="legal-h2">13. Контакты оператора</h2>

            <p class="legal-requisite"><span class="legal-requisite__label">ФИО:</span> Ковалёв Вячеслав Юрьевич</p>
            <p class="legal-requisite"><span class="legal-requisite__label">Налоговый статус:</span> плательщик налога на профессиональный доход (самозанятый)</p>
            <p class="legal-requisite"><span class="legal-requisite__label">ИНН:</span> 910618962001</p>
            <p class="legal-requisite"><span class="legal-requisite__label">Контактный e-mail:</span> kovalev.v.u@gmail.com</p>
            <p class="legal-requisite"><span class="legal-requisite__label">Сайт:</span> https://sites-shop.ru</p>
        </section>
    </article>
@endsection
