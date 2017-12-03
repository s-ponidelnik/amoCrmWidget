<?php

require_once 'amoCrmlib/amoCrm.php';//Подключение класа для работы с api amoCrm


/*Если пришли данные от виджета*/
if (isset($_POST['first_name']) && isset($_POST['email']) && isset($_POST['phone'])) {

    $amoCrm = new amoCrm();
    $name = $_POST['first_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];


    $responsible_user_id = null;

    /*Поиск контакта по емейлу и телефону*/
    $contact = $amoCrm->findContact($email, $phone);

    /*Контакт не найден*/
    if (!is_null($contact)) {

        if (isset($contact['responsible_user_id']))//У контакта указан ответственный пользователь
            $responsible_user_id = $contact['responsible_user_id'];
        if (empty($responsible_user_id))//У контакта не указан ответственный пользователь
            $responsible_user_id = $amoCrm->getResponsibleId();//Учитывая распределение назначаем ответств. пользователя

        $lead = $amoCrm->addLead($responsible_user_id);//Создаем сделку.

        if (is_array($lead['leads']['add']))
            if (isset($lead['leads']['add'][0]['id']) && isset($lead['leads']['add'][0]['id'])) {//Проверяем формат

                $lead_id = $lead['leads']['add'][0]['id'];//Id сделки

                $contact['linked_leads_id'][] = $lead_id;//Добавляем инфо о связанной сделке

                /*Обновление данных контакта, добавление связи контакт=>сделка*/
                $res = $amoCrm->updateContact($contact['id'], $responsible_user_id, $contact['linked_leads_id']);

                /*Создание задачи "Перезвонить клиенту"*/
                $res = $amoCrm->addTodayLeadTask($responsible_user_id, $lead_id);

                header('Refresh: 0; url=index.html#success');

            } else {//Неожиданные изменения в формате ответа
                throw new Exception('Add lead response format unexpectable', '400');
            }
    }

    /*Контакт не найден*/
    if (is_null($contact)) {

        $responsible_user_id = $amoCrm->getResponsibleId();//Учитывая распределение назначаем ответств. пользователя

        $lead = $amoCrm->addLead($responsible_user_id);
        if (is_array($lead['leads']['add']))
            if (isset($lead['leads']['add'][0]['id']) && isset($lead['leads']['add'][0]['id'])) {//Проверяем формат
                $lead_id = $lead['leads']['add'][0]['id'];

                /*Создание контакта*/
                $res = $amoCrm->addContact($email, $phone, $name, $responsible_user_id, $lead_id);

                /*Создание задачи "Перезвонить клиенту"*/
                $res = $amoCrm->addTodayLeadTask($responsible_user_id, $lead_id);

                header('Refresh: 0; url=index.html#success');
                
            } else {//Неожиданные изменения в формате ответа
                throw new Exception('Add lead response format unexpectable', '400');
            }
    }
} else {
    header('Refresh: 1; url=index.html#success');
}









