<?php

define('WEBSITE', 'https://www.jiandaoyun.com');
// 频率超限后是否重试
define('RETRY_IF_LIMITED', true);

class APIUtils
{

    private $urlGetWidgets;
    private $urlGetData;
    private $urlRetrieveData;
    private $urlCreateData;
    private $urlUpdateData;
    private $urlDeleteData;
    private $apiKey;

    function __construct($appId, $entryId, $apiKey)
    {
        $this->urlGetWidgets = WEBSITE . "/api/v1/app/{$appId}/entry/{$entryId}/widgets";
        $this->urlGetData = WEBSITE . "/api/v1/app/{$appId}/entry/{$entryId}/data";
        $this->urlRetrieveData = WEBSITE . "/api/v1/app/{$appId}/entry/{$entryId}/data_retrieve";
        $this->urlCreateData = WEBSITE . "/api/v1/app/{$appId}/entry/{$entryId}/data_create";
        $this->urlUpdateData = WEBSITE . "/api/v1/app/{$appId}/entry/{$entryId}/data_update";
        $this->urlDeleteData = WEBSITE . "/api/v1/app/{$appId}/entry/{$entryId}/data_delete";
        $this->apiKey = $apiKey;
    }


    function getHttpHeader()
    {
        return array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        );
    }

    /**
     * 发送HTTP请求
     * @param $method
     * @param $url
     * @param $reqData
     * @return mixed
     * @throws
     */
    function sendRequest($method, $url, $reqData)
    {
        $method = strtoupper($method);
        $con = curl_init((string)$url);
        $header = $this->getHttpHeader();
        if ($method == 'GET') {
            $url = $url . '?' . http_build_query($reqData);
            $con = curl_init((string)$url);
        } else {
            curl_setopt($con, CURLOPT_POSTFIELDS, json_encode($reqData));
        }
        curl_setopt($con, CURLOPT_HTTPHEADER, $header);
        curl_setopt($con, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($con, CURLOPT_HEADER, 0);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($con, CURLOPT_SSL_VERIFYHOST, 0);
        $result = json_decode(curl_exec($con));
        if ($result == null || property_exists($result, 'code') != null) {
            // 包含错误
            curl_close($con);
            if ($result->code == 8303 && RETRY_IF_LIMITED) {
                // 频率超限, 5s后重试
                sleep(5);
                return $this->sendRequest($method, $url, $reqData);
            } else {
                throw new Exception("请求错误 Error Code: " . $result->code . " Error Msg: " . $result->msg);
            }
        } else {
            curl_close($con);
            return $result;
        }
    }

    /**
     * 获取表单字段
     * @return array 表单字段
     */
    function getFormWidgets()
    {
        try {
            $result = $this->sendRequest('POST', $this->urlGetWidgets, array());
        } catch (Exception $e) {
            echo $e;
            return array();
        }
        return $result->widgets;
    }

    /**
     * 获取满足条件的表单数据
     * @param $dataId
     * @param $limit
     * @param $fields
     * @param $filter
     * @return mixed
     */
    function getFormData($dataId, $limit, $fields, $filter)
    {
        $data = array(
            'data_id' => $dataId,
            'limit' => $limit,
            'fields' => $fields,
            'filter' => $filter
        );
        try {
            $result = $this->sendRequest('POST', $this->urlGetData, $data);
        } catch (Exception $e) {
            echo $e;
            return array();
        }
        return $result->data;
    }

    /**
     * 获取满足条件的全部数据
     * @param $fields
     * @param $filter
     * @return array
     */
    function getAllData($fields, $filter)
    {
        $formData = [];
        $getNextPageData = function ($dataId) use (&$formData, $fields, $filter, &$getNextPageData) {
            $data = $this->getFormData($dataId, 100, $fields, $filter);
            if (!empty($data)) {
                $dataId = $data[count($data) - 1]->_id;
                foreach ($data as $v) {
                    array_push($formData, $v);
                }
                $getNextPageData($dataId);
            }
        };
        $getNextPageData('');
        return $formData;
    }

    /**
     * 查询单条数据
     * @param $dataId
     * @return mixed
     */
    function retrieveData($dataId)
    {
        $data = array('data_id' => $dataId);
        try {
            $result = $this->sendRequest('POST', $this->urlRetrieveData, $data);
        } catch (Exception $e) {
            echo $e;
            return array();
        }
        return $result;
    }

    /**
     * 创建一条新数据
     * @param $data
     * @return mixed
     */
    function createData($data)
    {
        try {
            $result = $this->sendRequest('POST', $this->urlCreateData, array(
                'data' => $data
            ));
        } catch (Exception $e) {
            echo $e;
            return array();
        }
        return $result->data;
    }

    /**
     * 更新单条数据
     * @param $dataId
     * @param $update
     * @return mixed
     */
    function updateData($dataId, $update)
    {
        $data = array(
            'data_id' => $dataId,
            'data' => $update
        );
        try {
            $result = $this->sendRequest('POST', $this->urlUpdateData, $data);
        } catch (Exception $e) {
            echo $e;
            return array();
        }
        return $result->data;
    }

    /**
     * 删除单条数据
     * @param $dataId
     * @return mixed
     */
    function deleteData($dataId)
    {
        $data = array('data_id' => $dataId);
        try {
            $result = $this->sendRequest('POST', $this->urlDeleteData, $data);
        } catch (Exception $e) {
            echo $e;
            return array();
        }
        return $result;
    }
}

$appId = "5b1747e93b708d0a80667400";
$entryId = "5b1749ae3b708d0a80667408";
$apiKey = "CTRP5jibfk7qnnsGLCCcmgnBG6axdHiX";
$api = new APIUtils($appId, $entryId, $apiKey);

// 获取表单字段
$widgets = $api->getFormWidgets();
print_r($widgets);

// 按条件查询表单字段
$data = $api->getFormData("", 10, ["_widget_1528252846720", "_widget_1528252846801"], array(
    "rel" => "and",
    "cond" => [array(
        "field" => "_widget_1528252846720",
        "type" => "text",
        "method" => "empty"
    )]
));
print_r($data);

// 获取全部数据
$formData = $api->getAllData([], array());
print_r($formData);

// 新建单条数据
$data = array(
    # 单行文本
    "_widget_1528252846720" => array(
        "value" => "123"
    ),
    # 子表单
    "_widget_1528252846801" => array(
        "value" => [array(
            "_widget_1528252846952" => array(
                "value" => "123"
            )
        )]
    ),
    # 数字
    "_widget_1528252847027" => array(
        "value" => 123
    ),
    # 地址
    "_widget_1528252846785" => array(
        "value" => array(
            "province" => "江苏省",
            "city" => "无锡市",
            "district" => "南长区",
            "detail" => "清名桥街道"
        )
    ),
    # 多行文本
    "_widget_1528252846748" => array(
        "value" => "123123"
    )
);
$createData = $api->createData($data);
print_r($createData);

// 更新单条数据
$data = array(
    # 单行文本
    "_widget_1528252846720" => array(
        "value" => "12345"
    ),
    # 子表单
    "_widget_1528252846801" => array(
        "value" => [array(
            "_widget_1528252846952" => array(
                "value" => "12345"
            )
        )]
    ),
    # 数字
    "_widget_1528252847027" => array(
        "value" => 123
    )
);
$updateResult = $api->updateData($createData->_id, $data);
print_r($updateResult);

// 查询单条数据
$retrieveData = $api->retrieveData($createData->_id);
print_r($retrieveData);

// 删除单条数据
$deleteResult = $api->deleteData($createData->_id);
print_r($deleteResult);