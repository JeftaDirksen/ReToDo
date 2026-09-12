# ReToDo

ReToDo is a small web application for managing recurring tasks. It runs as a PHP 8 application behind Apache and stores its SQLite database in a persistent Docker volume.

## Requirements

- Docker Engine
- Docker Compose v2
- PHPMailer

## Download PHPMailer

```sh
wget https://github.com/PHPMailer/PHPMailer/archive/master.zip
unzip master.zip -d src
mv src/PHPMailer-master src/PHPMailer
rm master.zip
```

## Run with Docker Compose

Start the application and the optional SQLite browser:

```sh
docker compose up -d --build
```

Open ReToDo at [http://localhost:8080](http://localhost:8080).

The default application port is `8080`. To use another host port, set `PORT` when starting Compose:

```sh
PORT=8888 docker compose up -d --build
```

The application will then be available at [http://localhost:8888](http://localhost:8888).

## SQLite browser

The Compose stack includes `sqlitebrowser`, which provides a browser-based interface for inspecting the application database.

- HTTP: [http://localhost:3000](http://localhost:3000)
- HTTPS: [https://localhost:3001](https://localhost:3001)

Inside the SQLite browser container, the database volume is mounted at `/retodo_data`. The database file is `/retodo_data/retodo.db`.

## Useful commands

```sh
# View service logs
docker compose logs -f

# Stop the services while keeping the database volume
docker compose down

# Show running services
docker compose ps
```

To remove the database and other managed volume data as well as the containers:

```sh
docker compose down -v
```

This permanently deletes the SQLite database stored in the `data` volume.

## Project structure

```text
.
├── compose.yaml       # Application and SQLite browser services
├── Dockerfile         # PHP 8 Apache image definition
└── src/
	└── html/
		├── index.php  # Application entry point
		└── manifest.json
```

## Current status

The application currently initializes the SQLite database and configuration automatically on first request. The landing page and login form are present, but login submission and task management are still under development.
