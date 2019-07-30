Для поддержки autodescover типов date и datetime правил "D:\dev. web\root\WSzf_1\library\Zend\Soap\Wsdl.php" (перечисление типов)

Для интересного: вставка в WSDL атрибутов: maxOccurs="1" minOccurs="0"
при передаче в PHPDoc типа такого:
* @var string-255 $examinationStatusNotes ___FOR_ZEND_minOccurs=0 ___FOR_ZEND_maxOccurs=1
в файле: "D:\dev. web\root\WSzf_1\library\Zend\Soap\Wsdl\Strategy\DefaultComplexType.php"
после строки:
$element->setAttribute('type', $this->getContext()->getType(trim($matches[1][0])));
патч:
// Patrick 
                $tempo = $property->getDocComment(); 
                if (preg_match('/___FOR_ZEND_minOccurs\s*=\s*(\d+|unbounded)/',$tempo,$matches)) { 
                        $element->setAttribute('minOccurs', $matches[1]); 
                        } 
                if (preg_match('/___FOR_ZEND_maxOccurs\s*=\s*(\d+|unbounded)/',$tempo,$matches)) { 
                        $element->setAttribute('maxOccurs', $matches[1]); 
                        } 
// Patrick end 


Для удаление из результата-xml строк <result> делаем так:
В файле "D:\dev. web\root\WSzf_1\library\Zend\Soap\Server.php" метод handle, после 
$this->_response = ob_get_clean();
Вставляем:
// tiki patch, deleting '<return xsi:type="SOAP-ENC:Struct">' and '</return>'
$this->_response = str_replace( '<return xsi:type="SOAP-ENC:Struct">', '', $this->_response);
$this->_response = str_replace( '</return>', '', $this->_response);
// tiki patch end