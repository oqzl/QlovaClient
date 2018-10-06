# QlovaClient: Clova Extensions Kit Test Client in PHP

## Overview
- Clovaスキルのテストクライアントです

## Description
- 簡易なスクリプトでClovaスキルの対話モデルのテストが可能なテストクライアントです。
- PHPで書いてます。適切に設定すればcomposerでインストール可能です。
- こんなのでもPackagistって登録できるのでしょうか。今度試してみます。
- 実はQlovaという名前のPHP版のCEK SDKも作ったんですが恥ずかしくて公開していません。
  - 恥ずかしくない状態に直してから公開します。mm

## Requirement
- PHP7系で動作確認していますが、多分PHP5系でも動くと思います。
- ext-json
- ext-curl

## Usage
- バッチ処理モード: [sample.php](https://github.com/oqzl/QlovaClient/blob/master/sample.php) 参照
- 対話モード: [interactive.php](https://github.com/oqzl/QlovaClient/blob/master/interactive.php) 参照

## Install
- composer.json に以下を記述すればインストールできると思います。
```
(snip)
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:oqzl/QlovaClient.git"
        }
    ],
(snip)
    "require-dev": {
        "oqzl/QlovaClient": "dev-master"
    },
(snip)
```

## Contribution
- プルリク歓迎します！

## Licence
- [MIT](https://github.com/oqzl/QlovaClient/blob/master/LICENSE) ということにしています。

## Author
- [oqzl](https://github.com/oqzl)
