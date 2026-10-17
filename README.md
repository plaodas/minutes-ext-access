# minutes-ext-access

[日本語](README.ja.md)

This repository is a stand-in service for calling [minutes](https://github.com/plaodas/minutes) (Python / FastAPI) from PHP / Laravel 11.

- frontend: React
- backend: PHP 8.4, Laravel 11
- db: MySQL
- authentication: JWT Bearer

Calls to minutes follow [external.md](https://github.com/plaodas/minutes/blob/main/docs/external.md). The browser never holds a minutes token. It calls only this app's API. The endpoint list is in [frontend/README.md](frontend/README.md).

## Related repositories

- [minutes](https://github.com/plaodas/minutes): transcription, summary, and action-item extraction from audio, running on a local CPU. This repository calls that service. External use is specified in [external.md](https://github.com/plaodas/minutes/blob/main/docs/external.md).
- [memoroku](https://github.com/plaodas/memoroku): a phone voice-memo app that shows text processed by minutes on a home-screen widget. It uploads a recording to minutes, waits for the transcript, and displays a short sentence.

## Layout

```text
.
├── docker-compose.yml          MySQL and Laravel
├── backend/                    PHP 8.4 / Laravel 11
│   ├── app/Http/Controllers/   Login and the minutes proxy
│   ├── app/Services/MinutesClient.php
│   ├── routes/api.php
│   ├── docker/entrypoint.sh    migrate and seed on startup
│   └── .env                    MINUTES_BASE_URL and MINUTES_PROVISION_SECRET
└── frontend/                   React / Vite
    └── src/
        ├── api.js              Calls to Laravel
        ├── auth/               JWT storage
        └── pages/              Login and the protected page
```

The browser JWT stays in `frontend`. The minutes provision secret and each user's minutes token stay in `backend`.

## Start

Start minutes first, and copy its `PROVISION_SECRET` into `MINUTES_PROVISION_SECRET` in `backend/.env`. If `.env` is missing, the container copies `.env.example` on startup. The default base URL is `http://host.docker.internal/api`.

```bash
docker compose up --build
```

MySQL and Laravel start. Laravel is at http://localhost:8000. In another terminal, start the frontend.

```bash
cd frontend
npm install
npm run dev
```

Open http://localhost:5173. Vite forwards `/api` to `http://localhost:8000`.

The seed creates the first login user.

- Email: `demo@example.com`
- Password: `password`

Run the tests with:

```bash
docker compose run --rm backend php artisan test
```

Composer's security audit blocks the current Laravel 11 release, so `backend/composer.json` sets `audit.block-insecure` to `false`.

## Use

1. After login, a page protected by the JWT opens.

   ![Login](docs/login.png)

2. Choose an audio file and press "議事録を作る". Accepted types are `.wav`, `.mp3`, `.m4a`, `.flac`, `.ogg`, `.opus`, or `audio/`.
3. The task appears under "これまでのタスク". While it runs, the app polls `stage` and shows one of: waiting, preprocessing, transcribing, formatting, done, failed, or cancelled. Error text is not shown on each row.

   ![Protected page](docs/tasks.png)

4. Opening a finished task renders `result.minutes` as Markdown. That field contains the formatted minutes, the summary, and the action items. The transcript is `result.transcript`, shown as plain text.

   ![Finished task](docs/result-top.png)

   ![Summary and transcript](docs/result.png)

5. Opening a failed task shows that task's error.

   ![Failed task](docs/failed.png)
