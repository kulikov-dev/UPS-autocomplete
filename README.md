### Address validation and auto-comlete using [UPS API](https://www.ups.com/tr/en/services/technology-integration/us-address-validation.page) for PHP 5.5 >=

The tool allows to work with UPS address validation tool through the SOAP, realizing operations of validation and auto-complete addresses. Contains two methods:
 * <b>validate_address</b> - to validate an address or get a list of possible address candidates;
 * <b>get_address_field_candidates</b> - to get a list of possible candidates for a specific field (like state, zip or address). 

Before work you need to initialize the tool with your credentials:
``` php
    /**
     * Setup API credentials
     */
    public function set_credentials()
    {
        $this->user_name = '';          // TODO initialize credentials from your storage.
        $this->password = '';
        $this->license_number = '';
    }
```

After that it is ready to work. Below are sample of auto-completing a field 'address':
``` php
try {
    $output = $ups_connector->get_address_field_candidates('US', $zip, $state, $city, $street, $type);
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
    die;
}
```
