# StayHub — Cloud Deployment Guide (Render & Vercel)

This guide walks you step-by-step through deploying **StayHub** to **Render.com** (Docker/PaaS) and **Vercel** (Serverless). Both options are pre-configured in this repository.

---

## 🎯 Quick Comparison: Render vs. Vercel for PHP

| Feature | Render.com (Recommended for PHP) | Vercel (Serverless PHP) |
|---|---|---|
| **Runtime** | Linux Docker Container (`php:8.2-apache`) | Serverless Functions (`vercel-php`) |
| **Filesystem** | Full Read/Write (Uploads & Sessions preserved) | Read-only (Sessions routed to `/tmp`) |
| **Configuration** | [`Dockerfile`](file:///c:/Users/ajay1/Collage/airbnb_project/Dockerfile) + [`render.yaml`](file:///c:/Users/ajay1/Collage/airbnb_project/render.yaml) | [`vercel.json`](file:///c:/Users/ajay1/Collage/airbnb_project/vercel.json) |
| **Setup Difficulty** | 🟢 Easiest (Native Apache + PHP) | 🟡 Medium (Serverless PHP) |
| **Database** | Free Cloud MySQL or SQLite | Cloud MySQL (Aiven, TiDB, Railway) or `/tmp` SQLite |

---

## 🗄️ Step 0: Get a Free Cloud MySQL Database

Both Render and Vercel host your PHP application. For a production MySQL database accessible from the cloud, you can use any of these **100% free tier** providers:

### Option A: Aiven (Recommended — Free Forever)
1. Sign up at [https://aiven.io](https://aiven.io).
2. Click **Create Service** $\to$ Select **MySQL** $\to$ Choose the **Free Plan**.
3. Note your connection details:
   - **Host:** e.g. `mysql-stayhub-xyz.aivencloud.com`
   - **Port:** e.g. `12345`
   - **User:** `avnadmin`
   - **Password:** `<your-aiven-password>`
   - **Database:** `defaultdb` (or create `airbnb_db`)
4. Open the Aiven web console $\to$ Run the SQL commands from [`database/schema.sql`](file:///c:/Users/ajay1/Collage/airbnb_project/database/schema.sql) and [`database/seed.sql`](file:///c:/Users/ajay1/Collage/airbnb_project/database/seed.sql).

### Option B: TiDB Cloud Serverless (Free Forever)
1. Sign up at [https://tidbcloud.com](https://tidbcloud.com).
2. Create a free Serverless cluster (100% MySQL compatible).
3. Copy connection parameters into your environment variables.

---

## 🚀 Option 1: Deploying to Render.com (Recommended)

Render provides native Docker hosting with Apache, meaning file uploads, sessions, and `.htaccess` rules work exactly as they do in XAMPP.

### Step 1: Push Project to GitHub
```bash
git init
git add .
git commit -m "Deploy StayHub to Render"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/stayhub.git
git push -u origin main
```

### Step 2: Create Web Service on Render
1. Log in to [https://dashboard.render.com](https://dashboard.render.com).
2. Click **New +** $\to$ **Web Service**.
3. Connect your GitHub repository (`stayhub`).
4. Render will automatically detect the [`Dockerfile`](file:///c:/Users/ajay1/Collage/airbnb_project/Dockerfile).
5. Configure the service settings:
   - **Name:** `stayhub-vacation-rentals`
   - **Region:** Any (e.g., Singapore or Oregon)
   - **Branch:** `main`
   - **Runtime:** `Docker`
   - **Plan:** `Free`

### Step 3: Add Environment Variables on Render
Under the **Environment** tab, add:
| Key | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_TYPE` | `mysql` |
| `DB_HOST` | `<your-cloud-mysql-host>` |
| `DB_PORT` | `<your-cloud-mysql-port>` |
| `DB_NAME` | `<your-cloud-mysql-dbname>` |
| `DB_USER` | `<your-cloud-mysql-user>` |
| `DB_PASS` | `<your-cloud-mysql-password>` |
| `RAZORPAY_KEY_ID` | `<your-razorpay-test-key-id>` |
| `RAZORPAY_KEY_SECRET` | `<your-razorpay-test-key-secret>` |
| `RAZORPAY_TEST_MODE` | `true` |

*(Alternatively, if your cloud provider gives a connection URL like `mysql://user:pass@host:port/dbname`, simply add `DATABASE_URL` and StayHub will parse it automatically!)*

### Step 4: Click "Create Web Service"
Render will build the Docker container and deploy your site to `https://stayhub-vacation-rentals.onrender.com`.

---

## ⚡ Option 2: Deploying to Vercel

Vercel is pre-configured with [`vercel.json`](file:///c:/Users/ajay1/Collage/airbnb_project/vercel.json) using the `vercel-php` serverless runtime.

### Step 1: Push Project to GitHub
Ensure [`vercel.json`](file:///c:/Users/ajay1/Collage/airbnb_project/vercel.json) is committed to your repository.

### Step 2: Import Project into Vercel
1. Log in to [https://vercel.com](https://vercel.com).
2. Click **Add New...** $\to$ **Project**.
3. Select your GitHub repository (`stayhub`).
4. Framework Preset: Leave as **Other**.
5. Root Directory: `./` (default).

### Step 3: Add Environment Variables in Vercel
Expand **Environment Variables** and add:
- `DB_TYPE`: `mysql`
- `DB_HOST`: `<your-cloud-mysql-host>`
- `DB_PORT`: `<your-cloud-mysql-port>`
- `DB_NAME`: `<your-cloud-mysql-dbname>`
- `DB_USER`: `<your-cloud-mysql-user>`
- `DB_PASS`: `<your-cloud-mysql-password>`
- `RAZORPAY_KEY_ID`: `<your-razorpay-test-key-id>`
- `RAZORPAY_KEY_SECRET`: `<your-razorpay-test-key-secret>`
- `RAZORPAY_TEST_MODE`: `true`
- `APP_DEBUG`: `false`

### Step 4: Deploy
Click **Deploy**. Vercel will bundle your PHP scripts into serverless functions and serve your static CSS/JS assets across its global Edge CDN.

> **Note on Vercel Filesystem:**
> Serverless platforms like Vercel have a read-only filesystem (except `/tmp`). StayHub's [`config/config.php`](file:///c:/Users/ajay1/Collage/airbnb_project/config/config.php) automatically detects Vercel (`$isVercel`) and routes session writes and SQLite copies to `/tmp/stayhub_sessions` so the application functions seamlessly without read-only errors.

---

## 🛠️ Running Database Setup on Cloud

Once your site is live on Render or Vercel:
1. Open your browser to:
   `https://your-domain.onrender.com/database/setup.php`
2. If connecting to your new cloud database for the first time, `setup.php` will initialize tables and seed mock data in 1-click.
3. Once initialized, the security lock file (`database/.installed`) activates to protect your live database from unauthorized resets.

---

*StayHub is now 100% cloud-ready for both Render and Vercel!*
