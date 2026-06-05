#!/usr/bin/env python3
"""Build fr_CA + fr_FR .po from the .pot, filling each msgstr by exact English msgid.

Paths are resolved relative to this file, so the script works from anywhere the
repo is checked out (or symlinked into a WordPress install). After running, compile
the .mo files with msgfmt or `wp i18n make-mo languages`.
"""
import re, io, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANGDIR = os.path.join(ROOT, "languages")
POT = os.path.join(LANGDIR, "consent-mode-v2.pot")

# English source msgid -> French translation. Québec register (fr_CA) is the
# primary market; fr_FR currently shares it (glosses "témoins (cookies)").
T = {
 "Your privacy": "Votre vie privée",
 "We use cookies to measure traffic and improve your experience. No non-essential cookie is set without your consent. You can change your choices at any time.": "Nous utilisons des témoins (cookies) pour mesurer l’audience et améliorer votre expérience. Aucun témoin non essentiel n’est activé sans votre consentement. Vous pouvez modifier vos choix en tout temps.",
 "Learn more": "En savoir plus",
 "Cookie preferences by category": "Préférences de témoins par catégorie",
 "Necessary cookies": "Témoins nécessaires",
 "always on, essential to the operation of the site.": "toujours actifs, essentiels au fonctionnement du site.",
 "Analytics": "Mesure d’audience",
 "Google Analytics, to understand how the site is used.": "Google Analytics, pour comprendre l’utilisation du site.",
 "Marketing & personalization": "Marketing et personnalisation",
 "advertising, remarketing (Google Ads) and content personalization.": "publicité, remarketing (Google Ads) et personnalisation des contenus.",
 "Accept all": "Tout accepter",
 "Reject all": "Tout refuser",
 "Customize": "Personnaliser",
 "Save my choices": "Enregistrer mes choix",
 "Manage cookies": "Gérer les témoins",
 "Consent Mode v2": "Consentement",
 "Add your GA4 measurement ID to activate the consent banner and the tag.": "Ajoutez votre identifiant de mesure GA4 pour activer la bannière de consentement et la balise.",
 "Settings": "Réglages",
 "Google Listings &amp; Ads is active. If it is connected to a Google Ads conversion, it injects a second gtag.js with a default consent region limited to the EEA (Canada is not included) - a duplicate tag and advertising that is not blocked for Quebec. Keep the Ads conversion disconnected, or disable GLA tag injection.": "Google Listings &amp; Ads est actif. S’il est relié à une conversion Google Ads, il injecte une 2e balise gtag.js avec un consentement par défaut limité à l’Europe (le Canada n’y est pas) — double balise et publicité non bloquée pour le Québec. Gardez la conversion Ads déconnectée, ou désactivez l’injection de balise de GLA.",
 'Facebook for WooCommerce is active. The Meta pixel is NOT governed by this banner (Consent Mode does not control fbq). Wire its consent to the Marketing category via the "facebook_signals_held" filter, otherwise the pixel fires despite "Reject all".': "Facebook for WooCommerce est actif. Le pixel Meta n’est PAS géré par cette bannière (Consent Mode ne gouverne pas fbq). Reliez son consentement à la catégorie Marketing via le filtre « facebook_signals_held », sinon le pixel se déclenche malgré « Tout refuser ».",
 "Invalid measurement ID (expected format: G-XXXXXXXXXX). Previous value kept.": "Identifiant de mesure invalide (format attendu : G-XXXXXXXXXX). Valeur précédente conservée.",
 "Settings saved.": "Réglages enregistrés.",
 "Auto (follow the visitor's system)": "Auto (selon le système du visiteur)",
 "Light": "Clair",
 "Dark": "Sombre",
 "Bottom bar (full width)": "Barre inférieure (pleine largeur)",
 "Floating card (bottom corner)": "Carte flottante (coin inférieur)",
 "Centered modal": "Fenêtre modale centrée",
 "Auto (site language)": "Auto (langue du site)",
 "French": "Français",
 "English": "Anglais",
 "This plugin is the SINGLE source of the site's Google tag. Do not inject gtag.js anywhere else (Insert Headers & Footers, GLA, Site Kit, GTM). The tag loads only after the visitor makes a choice.": "Cette extension est la SEULE source de la balise Google du site. N’injectez pas gtag.js ailleurs (Insert Headers & Footers, GLA, Site Kit, GTM). La balise ne se charge qu’après un choix de l’internaute.",
 "Tag & consent": "Balise et consentement",
 "GA4 measurement ID": "Identifiant de mesure GA4",
 "Privacy policy URL": "URL de la politique de confidentialité",
 "Leave blank to use the privacy page configured in WordPress.": "Laisser vide pour utiliser la page de confidentialité configurée dans WordPress.",
 "Advertising signals": "Signaux publicitaires",
 "Manage ad_storage / ad_user_data / ad_personalization (Google Ads). Adds a Marketing category to the banner.": "Gérer ad_storage / ad_user_data / ad_personalization (Google Ads). Ajoute une catégorie Marketing à la bannière.",
 "Re-ask after (days)": "Re-demander après (jours)",
 "Maximum 390 days (~13 months). Consent expires and the banner reappears.": "Maximum 390 jours (~13 mois). Le consentement expire et la bannière réapparaît.",
 "Banner language": "Langue de la bannière",
 "Appearance": "Apparence",
 "Primary color": "Couleur principale",
 'Used for the "Accept" button and links. Button text color is chosen automatically for contrast.': "Utilisée pour le bouton « Accepter » et les liens. La couleur du texte des boutons est choisie automatiquement pour le contraste.",
 "Theme": "Thème",
 "Position": "Position",
 "The bottom bar is recommended for strict Loi 25 (it blocks no interaction). The centered modal covers the page until a choice is made, which may be treated as a cookie wall.": "La barre inférieure est recommandée pour une conformité stricte à la Loi 25 (elle ne bloque aucune interaction). La fenêtre modale centrée couvre la page jusqu’à ce qu’un choix soit fait, ce qui peut être considéré comme un mur de témoins.",
 "Button corner radius": "Rayon des coins des boutons",
 "0 = square, 40 = pill.": "0 = carré, 40 = pilule.",
 "Re-open button": "Bouton de réouverture",
 'Show the floating "Manage cookies" button after a choice is made.': "Afficher le bouton flottant « Gérer les témoins » après un choix.",
 "If hidden, add a link with data-cmv2-open (e.g. in the footer menu) to let visitors re-open the manager.": "Si masqué, ajoutez un lien avec data-cmv2-open (p. ex. dans le menu du pied de page) pour permettre la réouverture du gestionnaire.",
 "Banner text (optional override)": "Texte de la bannière (remplacement facultatif)",
 "Heading": "Titre",
 "Description": "Description",
 "Leave blank to use the translated default. Plain text only.": "Laisser vide pour utiliser le texte traduit par défaut. Texte brut seulement.",
}

def unescape(s):
    return s.replace('\\"', '"').replace('\\n', '\n').replace('\\t', '\t').replace('\\\\', '\\')

def escape(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')

with io.open(POT, encoding="utf-8") as f:
    raw = f.read()

blocks = raw.split("\n\n")
header = ('msgid ""\nmsgstr ""\n'
 '"Project-Id-Version: Consent Mode v2 2.0.0\\n"\n'
 '"Report-Msgid-Bugs-To: \\n"\n'
 '"Last-Translator: Tamarak\\n"\n'
 '"Language-Team: __TEAM__\\n"\n'
 '"Language: __LANG__\\n"\n'
 '"MIME-Version: 1.0\\n"\n'
 '"Content-Type: text/plain; charset=UTF-8\\n"\n'
 '"Content-Transfer-Encoding: 8bit\\n"\n'
 '"Plural-Forms: nplurals=2; plural=(n > 1);\\n"')

def build(lang, team):
    hit = 0
    res = []
    for b in blocks:
        lines = b.split("\n")
        msgid_line = next((l for l in lines if l.startswith('msgid "')), None)
        if msgid_line is None:
            res.append(b); continue
        if msgid_line == 'msgid ""':
            res.append(header.replace("__LANG__", lang).replace("__TEAM__", team)); continue
        m = re.match(r'^msgid "(.*)"$', msgid_line)
        logical = unescape(m.group(1)) if m else None
        tr = T.get(logical, "")
        if tr:
            hit += 1
        new_lines = []
        for l in lines:
            if l.startswith('msgstr "'):
                new_lines.append('msgstr "%s"' % escape(tr))
            else:
                new_lines.append(l)
        res.append("\n".join(new_lines))
    return "\n\n".join(res), hit

for lang, team, suffix in [("fr_CA", "French (Canada)", "fr_CA"), ("fr_FR", "French (France)", "fr_FR")]:
    txt, hit = build(lang, team)
    path = os.path.join(LANGDIR, "consent-mode-v2-%s.po" % suffix)
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(txt if txt.endswith("\n") else txt + "\n")
    print("wrote %s  (translated %d / %d UI strings)" % (path, hit, len(T)))
