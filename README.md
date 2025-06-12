# Abwesenheits-Manager

Mit dem Plugin lassen sich abwesende Mitglieder:innen direkt auf der Startseite (Index), in einer Übersichtsliste sowie auf der Teamseite anzeigen. Die Darstellung ist flexibel anpassbar i den Einstellungen und bietet verschiedene Konfigurationsmöglichkeiten.<br>

- <b>Zusammengefasste Darstellung:</b> Es ist möglich, mehrere Accounts einer Person zusammenzufassen oder jeden Account einzeln aufzulisten.
- <b>Namensanzeige:</b> Wähle, ob der Accountname, einen von dem/der User:in definierter Spitzname oder eine Kombination aus beidem angezeigt werden soll.
- <b>Datumsanzeige:</b> Optional kann ein Zeitraum eingeblendet werden – entweder mit Start- und Rückkehrdatum oder nur dem Rückkehrdatum.<br>

## Startseite (Index)
Das Plugin bietet die Möglichkeit, alle aktuell als abwesend gemeldeten Accounts direkt auf der Startseite anzuzeigen. Dabei kann festgelegt werden, ob alle abwesenden Mitglieder:innen gemeinsam dargestellt werden oder ob eine getrennte Auflistung von Teammitgliedern und normalen Mitglieder:innen erfolgen soll.

## Liste aller Abwesenheiten
Zusätzlich erstellt das Plugin eine separate Seite, auf der alle aktuell abwesenden Accounts übersichtlich gelistet werden. Auch hier stehen verschiedene Darstellungsoptionen zur Verfügung. Im Gegensatz zur Startseite wird hier jedoch nicht zwischen Teammitgliedern und normalen Mitglieder:innen unterschieden. Du kannst außerdem festlegen, welche Gruppen Zugriff auf diese Seite haben.

## Teamseite
Optional können abwesende Teammitglieder:innen direkt auf der Teamseite hervorgehoben werden.

## Startdatum & Automatischer Post
Mitglieder:innen können im Profil nicht nur ein Rückkehrdatum, sondern auch ein Startdatum für ihre Abwesenheit festlegen. In Foren, in denen es üblich ist, Abwesenheiten in einem bestimmten Thread zu posten, übernimmt das Plugin diesen Schritt automatisch: Sobald eine Abwesenheit im Profil eingetragen wird, wird ein Beitrag im vorgesehenen Thread erstellt.

# Vorrausetzungen
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Einstellungen - Abwesenheits-Manager
- Startdatum
- Abwesene Mitglieder auf dem Index
- Unterscheidung Team und Mitglieder
- Teamseite
- Gruppe
- Teamgruppen
- Abwesenheiten zusammenfassen
- Anzeige Name
- Spitzname
- Zeitraum auf dem Index
- Liste aller Abwesenheiten
- Erlaubte Gruppen
- Listen PHP
- Listen Menü
- Listen Menü Template
- Automatischer Abwesenheitspost
- Thread<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.<vr>
Genauso kann auch das Listen-Menü angezeigt werden, wenn man das <a href="https://github.com/ItsSparksFly/mybb-lists">Automatische Listen-Plugin von sparks fly</a> verwendet.

# Neue Template-Gruppe innerhalb der Design-Templates
- Abwesenheits-Manager

# Neue Templates (nicht global!)
- awaymanager_index
- awaymanager_index_bit
- awaymanager_index_team
- awaymanager_list
- awaymanager_list_user
- awaymanager_showteam
- awaymanager_showteam_user
- awaymanager_showteam_user_none
- awaymanager_usercp_startdate

# Neue Variablen
- index_boardstats: {$away_manager_index}
- usercp_profile_away: {$awaystartdate}

# Neues CSS - away_manager.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
```css
.awaymanager_list-legend {
	display: flex;
	flex-wrap: nowrap;
	gap: 20px;
	justify-content: space-around;
	background: #0f0f0f url(../../../images/tcat.png) repeat-x;
	color: #fff;
	border-top: 1px solid #444;
	border-bottom: 1px solid #000;
	padding: 7px;
}

.awaymanager_list-legend-item {
	width: 100%;
	text-align: center;
}

.awaymanager_list-user {
	display: flex;
	flex-wrap: nowrap;
	gap: 20px;
	justify-content: space-around;
	padding: 7px;
}

.awaymanager_list-user-item {
	width: 100%;
	text-align: center;
}

.away_manager_showteam {
	background: #fff;
	width: 100%;
	border-radius: 7px;
	margin-bottom: 20px;
	border: 1px solid #ccc;
	padding: 1px;
}

.away_manager_showteam-header {
	font-weight: bold;
	background: #0066a2 url(../../../images/thead.png) top left repeat-x;
	color: #ffffff;
	border-bottom: 1px solid #263c30;
	padding: 8px;
	-moz-border-radius-topleft: 6px;
	-moz-border-radius-topright: 6px;
	-webkit-border-top-left-radius: 6px;
	-webkit-border-top-right-radius: 6px;
	border-top-left-radius: 6px;
	border-top-right-radius: 6px;
}

.away_manager_showteam-user {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	background: #f5f5f5;
	border: 1px solid;
	border-color: #fff #ddd #ddd #fff;
}

.away_manager_showteam-name {
	width: 75%;
}

.away_manager_showteam-time {
	width: 25%;
}
```

# Links
<b>Übersicht aller Abwesenheiten</b><br>
misc.php?action=away_manager

# Demo
