#!/bin/bash
# Helper-Script zum Pushen zu GitHub

REPO_URL="https://github.com/digital-magazin/gemini-image-generator.git"
REPO_SSH="git@github.com:digital-magazin/gemini-image-generator.git"

echo "=== Gemini Image Generator - Push zu GitHub ==="
echo ""
echo "Bitte wählen Sie eine Option:"
echo "1) HTTPS (mit Username/Token)"
echo "2) SSH (muss zuvor konfiguriert sein)"
echo "3) GitHub CLI verwenden (falls installiert)"
echo ""
read -p "Option [1-3]: " option

case $option in
    1)
        echo "Verwende HTTPS..."
        git remote set-url origin "$REPO_URL"
        echo "Bitte erstellen Sie das Repository auf GitHub, falls es noch nicht existiert:"
        echo "https://github.com/new"
        echo ""
        read -p "Drücken Sie Enter zum Pushen... "
        git push -u origin master
        ;;
    2)
        echo "Verwende SSH..."
        git remote set-url origin "$REPO_SSH"
        echo "Bitte erstellen Sie das Repository auf GitHub, falls es noch nicht existiert:"
        echo "https://github.com/new"
        echo ""
        read -p "Drücken Sie Enter zum Pushen... "
        git push -u origin master
        ;;
    3)
        if command -v gh &> /dev/null; then
            echo "Erstelle Repository mit GitHub CLI..."
            gh repo create digital-magazin/gemini-image-generator --private --source=. --remote=origin --push
        else
            echo "GitHub CLI ist nicht installiert. Bitte installieren Sie es zuerst."
        fi
        ;;
    *)
        echo "Ungültige Option"
        exit 1
        ;;
esac

echo ""
echo "Fertig! Prüfen Sie das Repository hier:"
echo "https://github.com/digital-magazin/gemini-image-generator"
