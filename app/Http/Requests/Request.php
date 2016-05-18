<?php

namespace Medlinker\Hybrid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class Request extends FormRequest
{
    /**
     * 一个默认错误码
     * @var int
     */
    protected $defaultErrcode = 1000;

    /**
     * 错误状态码和消息
     * @var array
     */
    protected $errMsg = [
        // field name => [ errcode, errmsg ],
        // e.g. 'id' => [1001, 'id is required!'],
    ];

    /**
     * 请求不同的控制器方法，配置不同的验证规则
     * @var array
     */
    protected $ruleConfig=[
        //controller's method name => rules
    ];


    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules()
    {
        // get action name
        $routeAction = app('router')->currentRouteAction();
        $method = trim( strrchr($routeAction, '@'), '@');
        $rules = [];

        // 是否有对应方法的验证规则
        if ( isset($this->ruleConfig[$method]) ) {
            $rules = $this->ruleConfig[$method];

            // 可以设置一个默认规则列表，没有对应规则时则用默认的
        } elseif ( ! empty($this->ruleConfig['__default'])) {
            $rules = $this->ruleConfig['__default'];
        }

        return $rules;
    }


    /**
     * Get the proper failed validation response for the request.
     *
     * @param  array  $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        if ($this->ajax() || $this->wantsJson()) {
            return new JsonResponse($errors, 200); // 验证失败也响应 200
        }

        return $this->redirector->to($this->getRedirectUrl())
                                        ->withInput($this->except($this->dontFlash))
                                        ->withErrors($errors, $this->errorBag);
    }


    /**
     * 自定义消息.
     * @param Validator $validator
     * @return array
     * e.g.
     * {
     *    "errcode": 1000,
     *    "errmsg": "The section three must be an array."
     * }
     */
    protected function formatErrors(Validator $validator)
    {
        $errMsgs = $validator->errors()->getMessages();

        foreach ($errMsgs as $key => $errMsg) {

            // 调整格式
            if ( isset($this->errMsg[$key]) ) {
                $msg = $this->errMsg[$key];

                if (! is_array($msg) || count($msg) < 2) {
                    throw new \InvalidArgumentException($key . ' 的错误消息配置格式有误');
                }

                return [
                    'errcode' => (int)$msg[0],
                    'errmsg'  => trim($msg[1]),
                ];
            }
        }

        // 默认提示第一个
        $errMsg = array_shift($errMsgs);

        // 调整格式
        return [
            'errcode' => $this->defaultErrcode,
            'errmsg'  => $errMsg[0],
        ];
    }

}
