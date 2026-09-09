#!/bin/bash
# Ferma i servizi del centro operativo (serve, queue worker, scheduler).
echo "stop control center…"
pkill -f "artisan serve"          2>/dev/null && echo "  serve fermato"     || true
pkill -f "artisan queue:work"     2>/dev/null && echo "  worker fermato"    || true
pkill -f "artisan schedule:work"  2>/dev/null && echo "  scheduler fermato" || true
echo "ok"
