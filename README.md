# Minutes ログインスターター

React から Laravel 11 API へログインし、JWT Bearer で保護されたページを表示します。

## 起動

```bash
docker compose up --build
```

別ターミナルでフロントエンドを起動します。

```bash
cd frontend
npm install
npm run dev
```

ブラウザで http://localhost:5173 を開きます。

- メールアドレス: `demo@example.com`
- パスワード: `password`

## API

| Method | Path | 認証 |
| --- | --- | --- |
| POST | `/api/login` | 不要 |
| GET | `/api/me` | Bearer |
| GET | `/api/dashboard` | Bearer |
| POST | `/api/logout` | Bearer |

```http
Authorization: Bearer <access_token>
```

## テスト

```bash
docker compose run --rm backend php artisan test
```

Composer の security audit が現行の Laravel 11 をブロックするため、`backend/composer.json` では `audit.block-insecure` を `false` にしています。
