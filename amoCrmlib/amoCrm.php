<?php

/**
 * Конфиг файл
 */
define('CONFIG_FILE', 'config/config.php');
/**
 * Обязательные параметры
 */
define('REQUIRED_CONFIG_DATA', ['USER_LOGIN', 'USER_HASH', 'SUBDOMAIN']);

/**
 * Class amoCrm
 * Класс для работы с api amoCrm
 */
class amoCrm
{

    /**
     * @var mixed Конфиг
     */
    private $_config;


    /**
     * @return array Конфиг значения по-умолчанию
     */
    private function defaultValues()
    {
        return [
            'LEAD_TITLE' => 'Заявка с сайта',
            'LEAD_STATUS_ID' => 7087609,
            'PHONE_CUSTOM_FIELD_CODE_ID' => 335309,
            'PHONE_CUSTOM_FIELD_ENUM' => 'MOB',
            'EMAIL_CUSTOM_FIELD_CODE_ID' => 335311,
            'EMAIL_CUSTOM_FIELD_ENUM' => 'PRIV',
            'TASK_TEXT' => 'Перезвонить клиенту',
            'LEAD_TASK_TYPE' => 1,
            'LEAD_TASK_ELEMENT_TYPE' => 2,
            'TASK_STATUS' => 0,
        ];
    }

    /**
     * Метод для получения обработки конфиг значения
     * @param $param
     * @return mixed|null
     */
    private function config($param)
    {
        if (isset($this->_config[$param]))
            return $this->_config[$param];
        elseif (isset($this->defaultValues()[$param]))
            return $this->defaultValues()[$param];
        else
            return null;

    }

    /**
     * Конструктор.
     * amoCrm constructor.
     * @throws Exception
     */
    public function __construct()
    {
        if (file_exists(dirname(__FILE__) . '/' . CONFIG_FILE))//Проверка существования конфиг файла
            $this->_config = require_once dirname(__FILE__) . '/' . CONFIG_FILE;
        else
            throw new Exception('Config file not found', '400');

        foreach (REQUIRED_CONFIG_DATA as $required_config_value) {//Проверка валидности конфиг-файла
            if (!isset($this->_config[$required_config_value]))
                throw new Exception('Not enough config data. ' . $required_config_value . ' must be set', '400');
        }

        $this->auth();//Выполняем авторизацию
    }

    /**
     * Авторизация
     */
    public function auth()
    {
        $res = $this->request('private/api/auth.php?type=json', [
            'USER_LOGIN' => $this->config('USER_LOGIN'),
            'USER_HASH' => $this->config('USER_HASH')
        ]);
    }

    /**
     * Проверка ответа от сервера
     * @param integer $code - Ответ сервера
     * @return bool
     * @throws Exception
     */
    private function checkRes($code)
    {
        $code = (int)$code;
        $errors = array(
            301 => 'Moved permanently',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable'
        );
        if ($code != 200 && $code != 204) {
            throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
        }
        return true;
    }

    /**
     * Запрос к Api
     * @param string $method - Метод
     * @param array $data - Request Data
     * @param bool $get - GET/POST запрос
     * @param bool $noCodeCheck - не выполнять проверку кода
     * @return mixed
     * @throws Exception
     */
    private function request($method, $data = [], $get = false, $noCodeCheck = false)
    {

        //Адрес запроса к апи.
        $link = 'https://' . $this->config('SUBDOMAIN') . '.amocrm.ru/' . $method;


        $curl = curl_init();



        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');

        /*Установка дополнительных данных в http_header, необходимо для ограничения выборки сделок по дате*/
        if (isset($data['http_headers'])) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $data['http_headers']);
            unset($data['http_headers']);
        }

        /*Установка post или get параметров*/
        if (!$get)
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        if (!empty($data) && !$get)
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        if (!empty($data) && $get) {
            $query = http_build_query($data, '', '&');
            $link = $link . '?' . $query;
        }

        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookie.txt');
        curl_setopt($curl, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/cookie.txt');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($noCodeCheck) {
            return null;
        }

        if ($this->checkRes($code)) {
            $responseData = json_decode($out, TRUE);
            $response = $responseData['response'];
            return $response;
        }
        return null;
    }

    /**
     * Создание сделки
     * @param integer $responsible_user_id - ответственный пользователь
     * @return mixed
     */
    public function addLead($responsible_user_id)
    {
        $leads['request']['leads']['add'] = array(
            array(
                'name' => $this->config('LEAD_TITLE'),
                'date_create' => time(),
                'status_id' => $this->config('LEAD_STATUS_ID'),
                'responsible_user_id' => $responsible_user_id
            )
        );
        return $this->request('private/api/v2/json/leads/set', $leads);
    }


    /**
     * Получение данных о контакте по id
     * @param integer $id - id контакта
     * @return mixed|null
     */
    public function getContactById($id)
    {
        $res = $this->request('private/api/v2/json/contacts/list', ['id' => $id], true);
        if (is_array($res['contacts']))
            return $res['contacts'][0];
        return null;
    }

    /**
     * Поиск контакта по емейлу/телефона
     * Поиск по емейлу является приоритетным
     * @param string $email - Email
     * @param string $phone - Телефон
     * @return mixed
     */
    public function findContact($email, $phone)
    {
        $findByEmail = null;
        $findByPhone = null;

        $res = $this->getContacts();
        if (!isset($res['contacts']))
            return null;

        foreach ($res['contacts'] as $contact) {
            foreach ($contact['custom_fields'] as $custom_field) {
                if (($custom_field['code'] == 'EMAIL' || $custom_field['code'] == 'PHONE') && is_null($findByEmail)) {
                    foreach ($custom_field['values'] as $custom_field_val) {
                        if ($custom_field['code'] == 'EMAIL' && $custom_field_val['value'] == $email) {
                            $findByEmail = $contact;
                            break;
                        } elseif ($custom_field['code'] == 'PHONE' && $custom_field_val['value'] == $phone) {
                            $findByPhone = $contact;
                        }
                    }
                }

            }
        }

        if (!is_null($findByEmail))
            return $findByEmail;
        elseif (!is_null($findByPhone))
            return $findByPhone;

        return null;

    }

    /**
     * Обновление данных контакта
     * @param integer $id - Id контакта
     * @param integer $responsible_user_id - Id ответственного пользователя
     * @param array $linked_leads_ids - Массив связанных сделок
     * @return mixed|null
     */
    public function updateContact($id, $responsible_user_id, $linked_leads_ids)
    {
        $data = [
            'request' =>
                [
                    'contacts' => [
                        'update' => [
                            ['id' => $id, "last_modified" => time(), 'responsible_user_id' => $responsible_user_id, 'linked_leads_id' => $linked_leads_ids]
                        ]
                    ]
                ]
        ];
        $res = $this->request('private/api/v2/json/contacts/set', $data, false);
        $contacts = $res['contacts'];
        if (is_array($contacts['update']))
            return $contacts['update'][0];
        return null;
    }

    /**
     * Добавление/создание контакта
     * @param string $email - Email
     * @param string $phone - Телефон
     * @param string $name - Имя
     * @param integer $responsible_user_id - Id ответственного пользователя
     * @param integer $linked_leads_id - Id связанной сделки
     * @return mixed|null
     */
    public function addContact($email, $phone, $name, $responsible_user_id, $linked_leads_id)
    {


        $data = [
            'request' =>
                [
                    'contacts' => [
                        'add' => [
                            [
                                'responsible_user_id' => $responsible_user_id,
                                'created_user_id' => 0,
                                "name" => $name,
                                'linked_leads_id' => [
                                    $linked_leads_id
                                ],
                                "custom_fields" => [
                                    [
                                        'id' => $this->config('PHONE_CUSTOM_FIELD_CODE_ID'),
                                        "values" => [
                                            [
                                                "value" => $phone,
                                                "enum" => $this->config('PHONE_CUSTOM_FIELD_ENUM')
                                            ]
                                        ]
                                    ],
                                    [
                                        'id' => $this->config('EMAIL_CUSTOM_FIELD_CODE_ID'),
                                        "values" => [
                                            [
                                                "value" => $email,
                                                "enum" => $this->config('EMAIL_CUSTOM_FIELD_ENUM')
                                            ],
                                        ]
                                    ],
                                ]
                            ]
                        ],
                    ]
                ]
        ];

        $res = $this->request('private/api/v2/json/contacts/set', $data, false);
        $contacts = $res['contacts'];

        if (is_array($contacts['add']))
            return $contacts['add'][0];

        return null;
    }

    /**
     * Получение списка контактов
     * @return mixed
     */
    public function getContacts()
    {
        $res = $this->request('private/api/v2/json/contacts/list', [], true);
        return $res;
    }

    /**
     * Получение id ответственного пользователя учитывая принцип распределения
     * @return mixed
     */
    public function getResponsibleId()
    {
        $users = $this->getLeadsUserRel();
        $userIds = array_keys($users);
        return array_shift($userIds);
    }

    /**
     * Добавление связанной задачи, на текущий день
     * @param integer $responsible_user_id - Id ответственного пользователя
     * @param integer $lead_id - Id сделки
     * @return mixed
     */
    public function addTodayLeadTask($responsible_user_id, $lead_id)
    {
        $res = $this->request('private/api/v2/json/tasks/set', [
            'request' => [
                'tasks' => [
                    'add' => [
                        [
                            'element_type' => $this->config('LEAD_TASK_ELEMENT_TYPE'),
                            'element_id' => $lead_id,
                            'status' => $this->config('TASK_STATUS'),
                            'text' => $this->config('TASK_TEXT'),
                            'responsible_user_id' => $responsible_user_id,
                            'date_create' => time(),
                            'complete_till' => strtotime("+1 day"),
                            'task_type' => $this->config('LEAD_TASK_TYPE')
                        ]
                    ]
                ]
            ]
        ], false);
        return $res;
    }

    /**
     * Получение данных о нагрузке по сделка у пользователей для работы системы распределения.
     * @return array
     */
    public function getLeadsUserRel()
    {
        $users = [];
        $res = $this->accountInfo();
        $account = $res['account'];
        if (isset($account['current_user']))
            $adminId = $account['current_user'];
        else
            $adminId = null;
        if (isset($account['users'])) {
            foreach ($account['users'] as $user)
                if ($user['id'] != $adminId)
                    $users[$user['id']] = [];
        }
        $res = $this->request('private/api/v2/json/leads/list', ['http_headers' => ['IF-MODIFIED-SINCE: ' . date('D, d M Y 0:0:1', time())]], TRUE);


        if (isset($res['leads'])) {
            foreach ($res['leads'] as $lead) {
                if ($lead['responsible_user_id'] != $adminId) {
                    if (!isset($users[$lead['responsible_user_id']][$lead['main_contact_id']]))
                        $users[$lead['responsible_user_id']][$lead['main_contact_id']] = 1;
                }
            }
            foreach ($users as $user => $userLeads) {
                $users[$user] = count($userLeads);
            }
            asort($users);
        }
        return $users;
    }

    /**
     * Получение инфо о текущем пользователе
     * @return mixed
     */
    public function accountInfo()
    {
        $res = $this->request('private/api/v2/json/accounts/current', [],true);
        return $res;

    }
}
