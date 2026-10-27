.PHONY: help up down restart logs ps shell test lint types build

help: ## عرض المساعدة
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## تشغيل الحاويات في الخلفية
	docker compose up -d

down: ## إيقاف الحاويات
	docker compose down

restart: down up ## إعادة تشغيل الحاويات

logs: ## عرض سجلات الحاويات
	docker compose logs -f

ps: ## حالة الحاويات
	docker compose ps

shell: ## فتح طرفية داخل حاوية التطبيق
	docker compose exec app bash

test: ## تشغيل اختبارات Pest
	docker compose exec app php artisan test

lint: ## فحص التنسيق بـ Pint و ESLint
	docker compose exec app ./vendor/bin/pint --test
	npm run lint

types: ## فحص الأنواع بـ Larastan و TypeScript
	docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
	npm run types

build: ## بناء أصول الواجهة
	npm run build
