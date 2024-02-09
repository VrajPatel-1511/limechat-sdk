<?php

// use Flits\Limechat\LimechatProvider;

use Carbon\Carbon;
use Flits\Limechat\API\Upload;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

require_once 'vendor/autoload.php';

class LimechatTest extends TestCase {

    public $client;
    public $CREDIT_UPDATE_EVENT_NAME='flits_store_credit_adjusted';
    public $CUSTOMER_PROFILE_UPDATE_EVENT_NAME='flits_customer_profile_updated';

    protected function setUp(): void {
        $mock = new MockHandler([
            new Response(200, [], '{"message": "Success"}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $this->client = new Client(['handler' => $handlerStack]);

    }

    public function createApplication() {
        $app = require __DIR__ . '/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.testing');
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }

    protected function tearDown(): void {
        parent::tearDown();
        Mockery::close(); 
    }

    public function getHeaders() {
        $config['headers'] = [
            "Content-Type" => "application/json",
            "x-limechat-access-token" => "*********"
        ];
        return $config;
    }

    public function UploadAPI($updatedCustomer,$extraData,$event_name) {
        $eventName = ($event_name == "update_customer_profile") ? $this->CUSTOMER_PROFILE_UPDATE_EVENT_NAME : $this->CREDIT_UPDATE_EVENT_NAME;
        $type=((isset($extraData->rule_id)) && ($extraData->rule_id == -1)) ? 'Adjusted by store owner' : 'Automatic adjustment by flits rule manager';
        $extraData->value=$extraData->value??0;
        $extraData->module_on=$extraData->module_on??'';
        $extraData->comment=$extraData->comment??'';

        $requestData = '{
            "d": [
                {
                    "identity":"'.$updatedCustomer['email'].'",
                    "type": "event",
                    "evtName": "'.$eventName.'",
                    "evtData": {
                        "adjusted_credit": '.$extraData->value.',
                        "old_credit":'.round((floatval(($updatedCustomer['credits'] / 100)) - floatval($extraData->value)), 2).',
                        "module_on":"'.$extraData->module_on.'",
                        "reason":"'.$extraData->comment.'",
                        "type":"'.$type.'",
                        "current_credit":'.($updatedCustomer['credits'] / 100).'
                    }
                },
                {
                    "identity":"'.$updatedCustomer['email'].'",
                    "type": "profile",
                    "profileData": {
                        "flits_current_credit": 30,
                        "Gender":"'.$updatedCustomer['gender'].'",
                        "Phone":"'.$updatedCustomer['phone'].'", 
                        "DOB":"'.(!is_null($updatedCustomer['birthdate']) ? '$D_'.Carbon::parse($updatedCustomer['birthdate'])->timestamp : '').'"
                    }
                }
            ]
        }';
        $limechatTrack = new Upload($this->getHeaders());
        $response=$limechatTrack->POST($requestData);
        $statusCode = $response->status;
        $this->assertEquals("success", $statusCode);
    }

    public function testProfileSave() {
        $request = [
            'first_name' => 'Vaibhav',
            'last_name' => 'Rathod',
            'phone' => '+919876543211',
            'gender' => 'male',
            'Company_name' => 'Flits',
            'token' => '91f4cea3d2641c5abbf7c73502eb984f',
            'from_page' => 'account',
            'birthdate' => '2002-08-15'
        ];
        $request['name'] = trim($request['first_name']) .' '. $request['last_name'];
        
        $app_id = 1;
        $user_id = 12;
        $customerIdToSearch = 7526853083456;

        $customerMock = Mockery::mock(Customer::class);

        $customerMock->shouldReceive('where')->with('customer_id', $customerIdToSearch)->once()->andReturnSelf();
        $customerMock->shouldReceive('first')->once()->andReturn([
            'id' => 1,
            'customer_id' => $customerIdToSearch,
            'name' => 'Vaibhav Rathod',
            'email'=>'vaibhav@getflits.com',
            'phone' => '+919876543211',
            'gender' => 'male',
            'Company_name' => 'Flits',
            'token' => '91f4cea3d2641c5abbf7c73502eb984f',
            'credits' => 50,
            'from_page' => 'account',
            'birthdate' => '2002-08-15'
        ]);
        $customer = $customerMock->where('customer_id', $customerIdToSearch)->first();
        // Assert the expected customer data
        $this->assertEquals(1, $customer['id']);
        $this->assertEquals('Vaibhav Rathod', $customer['name']);
        $this->assertEquals($customerIdToSearch, $customer['customer_id']); // Check the customer_id value

        // Now, update the customer record
        $customerMock->shouldReceive('where')->with('customer_id', $customerIdToSearch)->once()->andReturnSelf();
        $customerMock->shouldReceive('update')->with($request)->once()->andReturn(1);

        $updateResult = $customerMock->where('customer_id', $customerIdToSearch)->update($request);

        // Assert the update result
        $this->assertEquals(1, $updateResult);

        // Fetch the updated customer record
        $customerMock->shouldReceive('where')->with('customer_id', $customerIdToSearch)->once()->andReturnSelf();
        $customerMock->shouldReceive('first')->once()->andReturn(['id' => 1,
            'customer_id' => $customerIdToSearch,
            'name' => 'Vaibhav Rathod',
            'email'=>'vaibhav@getflits.com',
            'phone' => '+919876543211',
            'gender' => 'male',
            'Company_name' => 'Flits',
            'token' => '91f4cea3d2641c5abbf7c73502eb984f',
            'credits' => 50,
            'from_page' => 'account',
            'birthdate' => '2002-08-15']);

        // Fetch the customer record again
        $updatedCustomer = $customerMock->where('customer_id', $customerIdToSearch)->first();

        // Assert the updated customer data
        $this->assertEquals(1, $updatedCustomer['id']);
        $this->assertEquals('Vaibhav Rathod', $updatedCustomer['name']);
        $this->assertEquals($customerIdToSearch, $updatedCustomer['customer_id']);
        $event_name='update_customer_profile';

        $extraData = new \stdClass();
        $this->UploadAPI($updatedCustomer,$extraData,$event_name);
    }

    public function testCreditSave() {
        $customerIdToSearch = 7526853083456;

        $customerMock = Mockery::mock(Customer::class);

        $customerMock->shouldReceive('where')->with('customer_id', $customerIdToSearch)->once()->andReturnSelf();
        
        $customerMock->shouldReceive('first')->once()->andReturn(['id' => 1,
                'customer_id' => $customerIdToSearch,
                'name' => 'Vaibhav Rathod',
                'email'=>'vaibhav@getflits.com',
                'phone' => '+919876543211',
                'gender' => 'male',
                'Company_name' => 'Flits',
                'token' => '91f4cea3d2641c5abbf7c73502eb984f',
                'credits' => 50,
                'from_page' => 'account',
                'birthdate' => '2002-08-15'
            ]);

        $updatedCustomer = $customerMock->where('customer_id', $customerIdToSearch)->first();
        $extraData = new \stdClass();
        $extraData->value=28;
        $extraData->comment='Repeat customer';
        $extraData->data=[];
        $extraData->rule_id = (isset($extraData->data['rule_id'])) ? $extraData->data['rule_id'] : -1;
        $extraData->module_on = "admin_adjusted";
        
        $this->assertEquals(1, $updatedCustomer['id']);
        $this->assertEquals('Vaibhav Rathod', $updatedCustomer['name']);
        $this->assertEquals($customerIdToSearch, $updatedCustomer['customer_id']);
        $event_name='send_customer_notification';
        $this->UploadAPI($updatedCustomer,$extraData,$event_name);
    }
}
