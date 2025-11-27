# Repository zu GitHub hochladen

## Option 1: Repository auf GitHub erstellen und pushen (Empfohlen)

### Schritt 1: Repository auf GitHub erstellen

1. Gehen Sie zu https://github.com/new
2. Repository-Name: `gemini-image-generator`
3. Besitzer: `digital-magazin` (oder Ihr GitHub-Username)
4. Beschreibung: "WordPress Plugin für KI-Bildgenerierung mit Google Gemini 3 Pro Image"
5. Sichtbarkeit: **Private** oder **Public** (nach Bedarf)
6. **WICHTIG:** Lassen Sie die Optionen "Add a README file", "Add .gitignore" und "Choose a license" **UNCHECKED** (leer)
7. Klicken Sie auf **"Create repository"**

### Schritt 2: Repository pushen

Führen Sie diese Befehle aus:

```bash
cd /var/www/vhosts/digital-magazin.de/staging.digital-magazin.de/wp-content/plugins/gemini-image-generator

# Prüfen ob Remote korrekt ist
git remote -v

# Falls nötig, Remote setzen (ersetzen Sie USERNAME mit Ihrem GitHub-Username):
# git remote set-url origin https://github.com/USERNAME/gemini-image-generator.git

# Alle Dateien pushen
git push -u origin master
```

Bei der Authentifizierung:
- **HTTPS:** Sie werden nach Username und Personal Access Token gefragt
- **SSH:** Muss zuvor konfiguriert sein

---

## Option 2: Mit GitHub CLI (falls installiert)

```bash
cd /var/www/vhosts/digital-magazin.de/staging.digital-magazin.de/wp-content/plugins/gemini-image-generator

# Repository erstellen und pushen in einem Schritt
gh repo create digital-magazin/gemini-image-generator --private --source=. --remote=origin --push
```

---

## Option 3: Personal Access Token verwenden

1. Gehen Sie zu: https://github.com/settings/tokens/new
2. Erstellen Sie ein Token mit `repo` Berechtigung
3. Kopieren Sie das Token
4. Pushen Sie mit dem Token:

```bash
cd /var/www/vhosts/digital-magazin.de/staging.digital-magazin.de/wp-content/plugins/gemini-image-generator

# Remote mit Token-URL setzen (TOKEN durch Ihr Token ersetzen)
git remote set-url origin https://TOKEN@github.com/digital-magazin/gemini-image-generator.git

# Pushen
git push -u origin master
```

---

## Aktueller Status

- ✅ Git-Repository initialisiert
- ✅ Remote konfiguriert: `git@github.com:digital-magazin/gemini-image-generator.git`
- ⏳ Repository muss auf GitHub erstellt werden
- ⏳ Authentifizierung muss konfiguriert werden

## Nach dem Push

Nach erfolgreichem Push finden Sie das Repository unter:
https://github.com/digital-magazin/gemini-image-generator

