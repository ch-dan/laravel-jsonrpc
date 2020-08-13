<?php
declare(strict_types=1);

use Upgate\LaravelJsonRpc\Server\FormRequest;
use Upgate\LaravelJsonRpc\Server\FormRequestFactory;
use Upgate\LaravelJsonRpc\Server\RequestParams;

class FormRequestTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var FormRequestFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $container = new Illuminate\Container\Container();
        $container->bind(
            \Illuminate\Contracts\Translation\Translator::class,
            function () {
                return new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
            }
        );
        $this->factory = new FormRequestFactory($container);
    }

    public function testValidationPasses()
    {
        $formRequest = $this->factory->makeFormRequest(FormRequestTest_FormRequest::class);
        $formRequest->setRequestParams(RequestParams::constructNamed(['id' => 1, 'email' => 'test@example.com']));
        /** @var Illuminate\Contracts\Validation\Validator $validator */
        $validator = $this->factory->makeValidator($formRequest);
        $this->assertFalse($validator->fails());
    }

    public function testValidationFails()
    {
        $formRequest = $this->factory->makeFormRequest(FormRequestTest_FormRequest::class);
        $formRequest->setRequestParams(RequestParams::constructNamed(['email' => 'not an email']));
        /** @var Illuminate\Contracts\Validation\Validator $validator */
        $validator = $this->factory->makeValidator($formRequest);
        $this->assertTrue($validator->fails());
    }

    public function testValidationMessages()
    {
        $formRequest = $this->factory->makeFormRequest(FormRequestTest_FormRequest::class);
        $formRequest->setRequestParams(RequestParams::constructNamed([]));
        /** @var Illuminate\Contracts\Validation\Validator $validator */
        $validator = $this->factory->makeValidator($formRequest);
        $expectedMessages = [
            'id' => ['I need a nice id'],
            'email' => ['I really need a great email'],
        ];
        $this->assertSame($expectedMessages, $validator->getMessageBag()->toArray());
    }

    public function testNestedObjectsInParametersDoNotGetNullifiedByLaravelValidator()
    {
        $formRequest = $this->factory->makeFormRequest(FormRequestNestedObjectsTest_FormRequest::class);
        $requestParams = [
            'simpleParam1' => 'string',
            'simpleParam2' => 1,
            'complexParamArray' => [
                (object)[
                    'a' => 1,
                    'b' => 'first',
                ],
                (object)[
                    'a' => 2,
                    'b' => 'second',
                    'c' => (object)[
                        'inner' => 'inner'
                    ]
                ],
            ],
        ];
        $formRequest->setRequestParams(RequestParams::constructNamed($requestParams));
        $validator = $this->factory->makeValidator($formRequest);
        $this->assertFalse($validator->fails());
        $nestedObjectsArray = $formRequest->get('complexParamArray');
        $this->assertCount(2, $nestedObjectsArray);
        $this->assertEquals(
            (object)[
                'a' => 1,
                'b' => 'first',
            ],
            $nestedObjectsArray[0]
        );
        $this->assertEquals(
            (object)[
                'a' => 2,
                'b' => 'second',
                'c' => (object)[
                    'inner' => 'inner'
                ]
            ],
            $nestedObjectsArray[1]
        );
    }

}

class FormRequestTest_FormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'    => 'required|numeric',
            'email' => 'required|email'
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'    => 'I need :attribute',
            'email.required' => 'I really need :attribute',
        ];
    }

    public function attributes(): array
    {
        return [
            'id'    => 'a nice id',
            'email' => 'a great email',
        ];
    }

}
class FormRequestNestedObjectsTest_FormRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'simpleParam1' => 'string',
            'simpleParam2' => 'integer',
            'complexParamArray' => 'sometimes|array',
            'complexParamArray.*.a' => 'integer',
            'complexParamArray.*.b' => 'string',
            'complexParamArray.*.c.inner' => 'sometimes|string',
        ];
    }

}
