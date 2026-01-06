# Dux Call（病院連絡支援システム）

QR コードで病院を登録し、  
**「今、電話できるか」が一目で分かる**  
患者向け病院連絡支援システムです。

PHP（XAMPP）＋ React（Vite）で構築し、  
スマートフォン実機での動作確認まで行いました。

---

## 制作物概要

- QR コード読み取りで病院を簡単登録
- 複数病院の連絡先を一覧管理
- 受付時間内のみ電話可能（時間外は自動で無効化）
- 曜日別受付時間・例外日（休診等）に対応
- 高齢者を想定したシンプルな UI 設計

---

## 使用技術

### フロントエンド

- React
- TypeScript
- Vite
- QR コード読み取り

### バックエンド（課題提出用）

- PHP
- XAMPP

### データベース

- hospitals
- routes
- route_weekly_hours
- route_exceptions
- route_exception_hours
- news
- patient_registrations

---

## 工夫した点

- URL 入力不要の QR 登録 UX
- 受付時間ロジック
- スマホ実機（Android）での動作検証

---

## DB について

動作確認に使用した DB スキーマ・データを同梱しています。
