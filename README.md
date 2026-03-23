# hamkey
部室の鍵や部屋の予約などの状態を管理するツールです。

ホームページのソースコードは非公開なので、この部分だけ切り取って公開しています。

```
/(ドキュメントルート)
├── LICENSE
├── README.md
├── gaku-ura (ここに無いファイルはgaku-ura libから入手し結合)
│   ├── data
│   │   └── key_manager
│   │       ├── css
│   │       │   └── index.css
│   │       ├── html
│   │       │   └── index.html
│   │       ├── js
│   │       │   └── index.js
│   │       └── key_manager.conf
│   └── main
│       └── key_manager.php (☆)
└── key_manager
    └── index.php (☆を呼び出す)
```


## 使用ライブラリ
gaku-ura lib: https://github.com/satuki-k/gaku-ura_web_tool

