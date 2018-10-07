<?php

namespace oqzl\QlovaClient;

class QlovaClient
{
    const REQUEST_LAUNCH = 'LaunchRequest';
    const REQUEST_INTENT = 'IntentRequest';
    const REQUEST_SESSION_ENDED = 'SessionEndedRequest';

    protected $extensionServerUrl = false;
    protected $debugHeaders = [];

    protected $applicationId = false;

    protected $intentList = [];

    protected $sessionId = false;
    protected $deviceId = false;
    protected $userId = false;

    protected $requestObject;

    protected $responseObject;
    protected $session = [];

    protected $sessionEnded = false;

    protected $debug = false;

    /**
     * QlovaClient constructor.
     */
    protected function __construct() {
        if (getenv('DEBUG')) {
            $this->debug = true;
        }
        $this->registerBuiltInIntent();
    }

    /**
     * インスタンス取得、メソッドチェインで書きたかっただけ
     *
     * @return QlovaClient
     */
    public static function getInstance() {
        return new QlovaClient();
    }

    /**
     * Extensionサーバの設定
     *
     * @param string $url Extensionサーバのエンドポイント
     * @return QlovaClient
     */
    public function setExtensionServer($url) {
        $this->extensionServerUrl = $url;
        return $this;
    }

    /**
     * デバッグ用HTTPヘッダの設定
     *
     * @param string $name  デバッグ用リクエストを識別するためのHTTPリクエストヘッダ名
     * @param string $value 上記リクエストヘッダの値
     * @return QlovaClient
     */
    public function setDebugHeader($name, $value = 'true') {
        $this->debugHeaders[$name] = $value;
        return $this;
    }

    /**
     * アプリケーションIDの設定
     *
     * @param string $applicationId アプリケーションID
     * @return QlovaClient
     */
    public function setApplicationId($applicationId) {
        $this->applicationId = $applicationId;
        return $this;
    }

    /**
     * ビルトインインテントの正常系を登録
     * 複数の発話パターンを認識させたいときはいい感じに正規表現で記述する
     * インタラクティブモード用に簡易入力を追加（c,h,y,n）
     *
     * @return QlovaClient
     */
    protected function registerBuiltInIntent() {
        return $this
            ->registerIntent('Clova.CancelIntent', '/^(c|cancel|キャンセル)$/')
            ->registerIntent('Clova.GuideIntent',  '/^(h|help|ヘルプ)$/')
            ->registerIntent('Clova.YesIntent',    '/^(y|yes|はい)$/')
            ->registerIntent('Clova.NoIntent',     '/^(n|no|いいえ)$/');
    }

    /**
     * インテント情報登録
     * ややこしいので詳細は README 参照
     *
     * @param string $intentName     対話モデルに登録したインテント名
     * @param string $messagePattern インテントを発動させる発話パターン（PCRE正規表現）
     * @param array  $slots          スロット情報の配列。$messagePattern でのキャプチャ順序に対応
     * @return QlovaClient
     */
    public function registerIntent($intentName, $messagePattern, $slots = []) {
        $this->intentList[] = [
            'intentName'     => $intentName,
            'messagePattern' => $messagePattern,
            'slots'          => $slots,
        ];
        return $this;
    }

    /**
     * セッションID、デバイスID、ユーザID をランダムに生成して設定
     *
     * @return QlovaClient
     */
    public function newSession() {
        return $this->setSessionId()->setDeviceId()->setUserId();
    }

    /**
     * セッションIDを設定
     *
     * @param bool|string $sessionId セッションIDを指定
     * @return QlovaClient
     */
    public function setSessionId($sessionId = false) {
        $this->sessionId = $sessionId ?: self::getRandomId('long');
        return $this;
    }

    /**
     * デバイスIDを設定
     *
     * @param bool|string $deviceId デバイスIDを指定
     * @return QlovaClient
     */
    public function setDeviceId($deviceId = false) {
        $this->deviceId = $deviceId ?: self::getRandomId('long');
        return $this;
    }

    /**
     * ユーザIDを設定
     *
     * @param bool|string $userId ユーザIDを指定
     * @return QlovaClient
     */
    public function setUserId($userId = false) {
        $this->userId = $userId ?: self::getRandomId('short');
        return $this;
    }

    /**
     * LaunchRequestを送信
     *
     * @return QlovaClient
     */
    public function sendLaunchRequest() {
        return $this->send(false, self::REQUEST_LAUNCH);
    }

    /**
     * SessionEndedRequestを送信
     * ※これって実際に送られてきてる？？
     *
     * @return QlovaClient
     */
    public function sendSessionEndedRequest() {
        return $this->send(false, self::REQUEST_SESSION_ENDED);
    }

    /**
     * IntentRequestを送信
     *
     * @param string $message 発話内容
     * @return QlovaClient
     */
    public function sendIntentRequest($message) {
        return $this->send($message);
    }

    /**
     * リクエスト送信
     * sendIntentRequest() の短縮形としても利用可能
     *
     * @param string $message IntentRequestのときの発話内容
     * @param string $type    リクエスト種別
     * @return QlovaClient
     */
    public function send($message, $type = self::REQUEST_INTENT) {
        // TODO: 設定チェック（スキルサーバ、各種ID）

        // セッションが終了していたらエラー
        if ($this->sessionEnded) {
            $this->log('(Session Ended)');
            return $this;
        }

        switch ($type) {
            case self::REQUEST_LAUNCH:
            case self::REQUEST_SESSION_ENDED:
                $this->log('', $type);
                $this->createRequestObject($type);
                break;

            case self::REQUEST_INTENT:
                $this->log($message, $type);
                $intent = $this->createIntentObject($message);
                $this->createRequestObject($type, $intent);
                break;
        }

        // リクエスト投げる
        // TODO: Guzzle使う？ https://github.com/guzzle/guzzle
        $this->request();

        // レスポンスを解釈する（QlovaResponse使いたい…）
        // セッション情報取得
        $this->serveSession();

        // メッセージ抽出
        $messages = self::getResponseMessages($this->responseObject['response']);
        foreach ($messages as $msg) {
            // いい感じにログを吐く
            $this->log($msg, '->');
        }

        // Reprompt抽出
        if (isset($this->responseObject['response']['reprompt'])) {
            $reprompt = self::getResponseMessages($this->responseObject['response']['reprompt']);
            foreach ($reprompt as $msg) {
                // いい感じにログを吐く
                $this->log($msg, 'reprompt->');
            }
        }

        return $this;
    }

    /**
     * インテント解析
     *
     * @param string $message 発話内容
     * @return array|bool 登録したインテント情報に応じて発話を解析した結果
     */
    protected function createIntentObject($message) {
        $intent = [];
        foreach ($this->intentList as $item) {
            if ($intent = $this->matchIntent($message, $item)) {
                break;
            }
        }
        $this->debugLog($intent, 'intent');
        return $intent;
    }

    /**
     * インテント判定
     *
     * @param string $message    発話内容
     * @param array  $intentItem 登録したインテント情報
     * @return array|bool 登録したインテント情報に応じて発話を解析した結果、マッチした場合はインテント名とスロット情報を返す
     */
    protected function matchIntent($message, $intentItem) {
        $intent = false;
        if (preg_match($intentItem['messagePattern'], $message, $match)) {
            $slots = [];
            if (!empty($intentItem['slots'])) {
                $idx = 1; // $match[0] はマッチしたパターン全体なのでスキップする
                foreach ($intentItem['slots'] as $name => $delegate) {
                    if ($delegate !== false || isset($match[$idx])) {
                        $slots[$name] = [
                            'name' => $name,
                            'value' => $delegate ?: $match[$idx],
                        ];
                    }
                    $idx ++;
                }
            }
            $intent = [
                'name' => $intentItem['intentName'],
                'slots' => $slots,
            ];
        }
        return $intent;
    }

    /**
     * リクエストオブジェクト構築
     *
     * @param string $requestType リクエスト種別
     * @param array  $intent
     */
    protected function createRequestObject($requestType = self::REQUEST_INTENT, $intent = []) {
        $user = [
            'userId' => $this->userId,
            'accessToken' => self::getRandomId('long'),
        ];
        $session = [
            'sessionId' => $this->sessionId,
            'sessionAttributes' => $this->session,
            'user' => $user,
            'new' => ($requestType == 'launch'),
        ];
        $context = [
            'System' => [
                'application' => [
                    'applicationId' => $this->applicationId,
                ],
                'user' => $user,
                'device' => [
                    'deviceId' => $this->deviceId,
                    'display' => false,
                ],
            ],
        ];
        $request = [
            'type' => $requestType,
            'intent' => $intent,
        ];
        $requestObject = [
            'version' => '1.0',
            'session' => $session,
            'context' => $context,
            'request' => $request,
        ];
        $this->debugLog($requestObject, 'request');
        $this->requestObject = $requestObject;
    }

    /**
     * リクエスト処理
     * 失敗したら落ちるようにしている
     */
    protected function request() {
        $headers = ['Content-Type: application/json'];
        foreach ($this->debugHeaders as $key => $value) {
            $headers[] = sprintf('%s: %s', $key, $value);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->extensionServerUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($this->requestObject),
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            $this->log(['HTTP_STATUS' => $status], 'error');
            exit;
        }

        $responseObject = json_decode($response, true);
        if ($responseObject == false) {
            $this->log(curl_error($ch), 'error');
            exit;
        }

        $this->debugLog($responseObject, 'response');
        $this->responseObject = $responseObject;
    }

    /**
     * セッション終了判定（使ってない）
     *
     * @return bool
     */
    public function sessionEnded() {
        return $this->sessionEnded;
    }

    /**
     * セッション情報の処理
     * レスポンスのセッション情報を次のセッションで送信する
     */
    protected function serveSession() {
        // セッションが終了していたら終了フラグをセット
        if (isset($this->responseObject['response']['shouldEndSession'])) {
            $this->sessionEnded = $this->responseObject['response']['shouldEndSession'];
        }
        // セッションが終了していなければ $this->responseObject からセッション情報を抽出
        if (!$this->sessionEnded && isset($this->responseObject['sessionAttributes'])) {
            $session = $this->responseObject['sessionAttributes'];
            $this->debugLog($session, 'session');
            $this->session = $session;
        }
    }

    /**
     * レスポンスからメッセージを取得
     *
     * @param array $response レスポンスオブジェクト
     * @return array 返ってきたメッセージ
     */
    protected static function getResponseMessages($response) {
        $messages = [];
        if (isset($response['outputSpeech']['values'])) {
            $values = $response['outputSpeech']['values'];
            if (isset($values['type'])) {
                $messages[] = $values['value'];
            } else {
                foreach ($values as $msg) {
                    $messages[] = $msg['value'];
                }
            }
        }
        return $messages;
    }

    /**
     * @param bool $type ランダムなIDを長さ指定に応じて生成
     * @return bool|string
     */
    protected static function getRandomId($type = false) {
        switch ($type) {
            case 'long':
                $id = sprintf('%s-%s-%s-%s-%s', self::r(8), self::r(4), self::r(4), self::r(4), self::r(12));
                break;
            case 'short':
                $id = self::r(22);
                break;
            default:
                $id = self::r(40);
                break;
        }
        return $id;
    }

    /**
     * ランダムな英数文字列を生成
     *
     * @param int $length 長さ
     * @return bool|string
     */
    protected static function r($length = 8) {
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyz', $length)), 0, $length);
    }

    /**
     * デバッグ用ログ出力
     * 環境変数 DEBUG がセットされているときのみ出力
     *
     * @param array $object ログに出力したいオブジェクト
     * @param string $label ログに出力したいラベル
     */
    protected function debugLog($object, $label = 'log') {
        if ($this->debug) {
            $this->log($object, $label);
        }
    }

    /**
     * ログ出力（標準出力）
     *
     * @param array|string $object ログに出力したいオブジェクト
     * @param string       $label ログに出力したいラベル
     */
    protected function log($object, $label = 'log') {
        if ($object == false) {
            echo $label . "\n";
        } else {
            echo $label . ': ' . json_encode($object, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
