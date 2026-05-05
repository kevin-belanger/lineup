#!/bin/bash

cd "$(dirname "$0")/.."

./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
