#!/bin/sh

rm hooks-reference.zip

echo "Building"
npm run build
composer install --no-dev

echo "Creating archive... 🎁"
zip -r "hooks-reference.zip" \
	hooks-reference.php \
	admin \
	assets \
	includes \
	vendor \
	composer.json \
	readme.md \
	readme.txt \
	src \
	build/