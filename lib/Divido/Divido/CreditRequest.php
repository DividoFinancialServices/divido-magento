<?php

class Divido_CreditRequest extends Divido_ApiResource
{
  /**
   * @param string $id The ID of the charge to retrieve.
   * @param string|null $apiKey
   *
   * @return Divido_Charge
   */
  public static function retrieve($id, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedRetrieve($class, $id, $apiKey);
  }

  /**
   * @param array|null $params
   * @param string|null $apiKey
   *
   * @return array An array of Divido_Charges.
   */
  public static function all($params=null, $apiKey=null)
  {
    $class = get_class();
    return self::_scopedAll($class, $params, $apiKey);
  }

  /**
   * @param array|null $params
   * @param string|null $apiKey
   *
   * @return Divido_Charge The created charge.
   */
  public static function create($params=null, $merchant=null)
  {
    $class = get_class();
    return self::_scopedCreditRequest($class, $params, $merchant);
  }

  /**
   * @return Divido_Charge The saved charge.
   */
  public function save()
  {
    $class = get_class();
    return self::_scopedSave($class);
  }

  /**
   * @param array|null $params
   *
   * @return Divido_Charge The refunded charge.
   */
  public function refund($params=null)
  {
    $requestor = new Divido_ApiRequestor($this->_apiKey);
    $url = $this->instanceUrl() . '/refund';
    list($response, $apiKey) = $requestor->request('post', $url, $params);
    $this->refreshFrom($response, $apiKey);
    return $this;
  }

 
}
