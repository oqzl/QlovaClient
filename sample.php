<?php

require_once __DIR__ . '/vendor/autoload.php';

use oqzl\QlovaClient\QlovaClient;

QlovaClient::getInstance()

    // ExtensionサーバのURLを設定します。 **なお値は全てサンプルです！このままでは動作しません！**
    ->setExtensionServer('https://clova-extension.example.com/sample/')

    // 本クライアントはSIGNATURECEKヘッダを送信しません
    // Extensionサーバ側で署名の代わりに検証するヘッダ名と値をここで指定します
    ->setDebugHeader('X-Debug-Clova-Skill', 'SUPER_SECRET')

    // リクエストに含まれるアプリケーションID（Extension ID）を指定します
    ->setApplicationId('com.example.clova-extension.sample')

    // Extensionの対話モデルに設定したカスタムインテントに対応した情報を設定します
    // 第1引数 $intentName     : カスタムインテント名
    // 第2引数 $messagePattern : 正常系の発話パターンをPCRE正規表現で設定。() でキャプチャした部分はスロットに設定されます
    // 第3引数 $slots          : スロットの情報を連想配列で設定。ここが手抜きでややこしい
    //   連想配列のキー : スロット名
    //   連想配列の値   : falseの場合は $messagePattern の中でキャプチャしたパターン、文字列の場合はその値
    //     例） registerIntent('SampleIntent', '/^(ラーメン|チャーハン)$/', ['menu' => false]); … スロット「menu」には「ラーメン」または「チャーハン」が入る
    //     例） registerIntent('SampleIntent', '/^(僕|私|某|拙者)$/', ['me' => '僕']); … スロット「me」には「僕」が入る
    //     例） ↓ このパターンだと「12/24」という発話に対してスロット「month」には「12」、スロット「day」には「24」が入る
    ->registerIntent('BirthDayIntent', '/^(\\d+)/(\\d+)$/', ['month' => false, 'day' => false])

    // ランダムなセッションID、デバイスID、ユーザIDを生成
    ->newSession()

    // LaunchRequest（らしきもの）を送信
    ->sendLaunchRequest()

    // IntentRequest（らしきもの）を送信
    ->send('9/21') // BirthDayIntent が送信される。スロット「month」は「9」、スロット「day」には「21」が入る
    ->send('y')    // Clova.YesIntent が送信される
    ->send('c');   // Clova.CancelIntent が送信される

exit;
