# 📦 Laravel Docker Setup

This repository provides a ready-to-use Docker environment for running Laravel applications easily.  
You can clone this repo and install your Laravel project inside the `main-app` folder.

---

## 🚀 Features

- Laravel-ready Docker setup
- PHP + Apache
- MySQL support
- Easy project mounting via `main-app`
- Beginner-friendly structure

---

## 📁 Project Structure
```
laravel-docker/
│
├── main-app/ # Your Laravel project goes here
├── docker/ # Docker configuration files
├── docker-compose.yml
├── Dockerfile
└── README.md

```
---

## ⚙️ Requirements

Make sure you have installed:

- Docker
- Docker Compose

---

## 🛠️ Installation Guide

### 1. Clone the repository

```bash
git clone https://github.com/coderemon24/laravel-docker.git
cd laravel-docker
cd main-app
composer create-project laravel/laravel .
cd ..
docker-compose up -d --build
docker exec -it app bash
php artisan migrate

```
