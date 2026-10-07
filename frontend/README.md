# minutes-ext-access

このリポジトリは、[minutes](https://github.com/plaodas/minutes)（Python / FastAPI）を PHP / Laravel 11 と連携させて利用するための疑似環境です。

- frontend: React
- backend: PHP 8.4、Laravel 11
- db: MySQL
- 認証: JWT Bearer

minutes の呼び出しは [external.md](https://github.com/plaodas/minutes/blob/main/docs/external.md) の仕様に従います。ブラウザは minutes のトークンを持たず、このアプリの API だけを呼びます。

## このアプリの API

ログインで受け取った `access_token` を、以降のリクエストに付けます。

```http
Authorization: Bearer <access_token>
```

| Method | Path | 認証 | 内容 |
| --- | --- | --- | --- |
| POST | `/api/login` | 不要 | ログイン。`access_token` を返す |
| GET | `/api/me` | Bearer | ログイン中のユーザー |
| POST | `/api/logout` | Bearer | ログアウト |
| GET | `/api/dashboard` | Bearer | 保護されたページの表示データ |
| GET | `/api/minutes/tasks` | Bearer | タスク一覧 |
| POST | `/api/minutes/tasks` | Bearer | 音声を multipart の `file` で送る。`task_id` を返す |
| GET | `/api/minutes/tasks/{taskId}` | Bearer | `stage` を返す |
| GET | `/api/minutes/tasks/{taskId}/result` | Bearer | `stage` が `success` の結果 |

音声の拡張子は `.wav`、`.mp3`、`.m4a`、`.flac`、`.ogg`、`.opus`、または `audio/` の Content-Type です。

## minutes 側の endpoint

Laravel が [external.md](https://github.com/plaodas/minutes/blob/main/docs/external.md) に基づいて呼ぶ endpoint です。ベース URL は `MINUTES_BASE_URL`（末尾は `/api`）です。

| Method | Path | 認証 | このアプリの対応 |
| --- | --- | --- | --- |
| POST | `/api/external/users` | `MINUTES_PROVISION_SECRET` | 初回利用時と、401 `Not authenticated` のときにユーザーとトークンを発行する |
| POST | `/api/transcribe-upload-bg` | 保存した minutes トークン | `POST /api/minutes/tasks` |
| GET | `/api/bg/tasks` | 保存した minutes トークン | `GET /api/minutes/tasks` |
| GET | `/api/bg/status/{task_id}` | 保存した minutes トークン | `GET /api/minutes/tasks/{taskId}` |
| GET | `/api/bg/result/{task_id}` | 保存した minutes トークン | `GET /api/minutes/tasks/{taskId}/result` |

進捗の SSE（`GET /api/bg/events`）は使いません。状態は status のポーリングで見ます。

本文だけの取得 endpoint（`GET /api/bg/minutes/{task_id}`、`GET /api/bg/transcript/{task_id}`、`GET /api/bg/summary/{task_id}`、`GET /api/bg/action-items/{task_id}`）は呼びません。整形済み議事録、要約、アクションアイテムは `result.minutes` を表示し、文字起こしは `result.transcript` を表示します。
