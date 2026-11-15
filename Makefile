# Variable Docker Compose
DC = docker compose

.PHONY: up stop restart bash-app bash-node

# Menjalankan seluruh kontainer di background
up:
	$(DC) up -d

# Memberhentikan seluruh kontainer
stop:
	$(DC) stop

# Merestart seluruh kontainer
restart:
	$(DC) restart

# Masuk ke dalam terminal/bash kontainer 'app' (PHP / Laravel)
bash-app:
	$(DC) exec app bash

# Masuk ke dalam terminal/bash kontainer 'node'
bash-node:
	$(DC) exec node sh