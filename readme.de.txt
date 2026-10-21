=== eInvoice Viewer for PEPPOL & EN16931 ===
Contributors: naranili
Tags: e-invoice, peppol, zugferd, xrechnung, factur-x
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 2.37.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

E-Rechnungen (ZUGFeRD, Factur-X, XRechnung, CII, UBL) per Shortcode auf Ihrer Seite zeigen. Im Browser gelesen, nichts wird gespeichert.

== Description ==

**➜ [Live-Demo ausprobieren](https://invoicepress.de/en/solutions/einvoice-tools/einvoice-viewer-for-peppol-en16931/einvoice-viewer-for-peppol-en16931-demo/)** – laden Sie eine XRechnung, ZUGFeRD/Factur-X- oder Peppol-BIS-Rechnung hoch und sehen Sie sie sofort lesbar dargestellt.

eInvoice Viewer for PEPPOL & EN16931 macht aus einer hochgeladenen elektronischen Rechnung eine übersichtliche, strukturierte HTML-Ansicht direkt auf Ihrer Seite - ohne externen Dienst und ohne die Datei zu speichern.

Fügen Sie den Shortcode `[spev_einvoice_viewer]` in eine Seite ein, und Ihre Besucher können eine elektronische Rechnung per Drag & Drop in den Viewer ziehen. **Das Dokument wird vollständig im Browser des Besuchers gelesen und dargestellt - es wird nie auf Ihren Server hochgeladen.** Das Ergebnis (Rechnungskopf, Beteiligte, Bestell- und Lieferdaten, Zahlungsangaben, Rechnungspositionen, Umsatzsteueraufschlüsselung und Summen) erscheint sofort, ohne Neuladen der Seite und ohne dass auch nur ein einziges Byte der Rechnung das Netzwerk durchquert. Das Layout orientiert sich am offiziellen ZUGFeRD-Viewer Quba und wird in einem Shadow DOM dargestellt, sodass es in jedem Theme gleich aussieht.

= Unterstützte Formate =

* **ZUGFeRD / Factur-X (PDF)** - das eingebettete XML wird automatisch extrahiert und gelesen.
* **CII (UN/CEFACT) XML** - wird direkt gelesen.
* **UBL / XRechnung (XML)** - wird direkt gelesen. Deckt XRechnung-UBL und Peppol BIS Billing 3.0 ab, einschließlich Gutschriften.

= Funktionen =

* **Drag-&-Drop-Uploader** - reines JavaScript, kein Framework, kein Neuladen der Seite.
* **Vollständige Feldabdeckung** - der Feldkatalog kennt 268 EN-16931-Business-Terms in 43 Business Groups. Leere Felder werden bewusst als Platzhalter angezeigt, damit Sie sehen, welche Terme der Standard vorsieht und welche diese Rechnung weglässt. Jedes Bankkonto und jedes Zahlungsmittel der Rechnung wird angezeigt, nicht nur das erste.
* **Beträge, die nicht aufgehen, werden markiert** - der Viewer rechnet Positionsbeträge, Umsatzsteueraufteilung und Summen nach. Ein ausgewiesener Betrag, der vom nachgerechneten abweicht, ist rot markiert, und darunter stehen beide Beträge.
* **Felder, die eine Regel aus der Rechnung selbst verletzen, werden markiert** - ein fehlendes Pflichtfeld, eine Umsatzsteuerkategorie, deren Regeln nicht erfüllt sind, ein Fälligkeitsdatum vor dem Rechnungsdatum oder ein Zeitraum, der vor seinem Anfang endet, ist rot markiert, und darunter steht ein Hinweis, der die verletzte Regel nennt, wo die Norm eine hat. Markierte Felder bleiben sichtbar, auch wenn „Nur gefüllte Felder“ eingeschaltet ist. Ein Kasten über der Rechnung sagt, was der Viewer prüft - die Pflichtfelder, die Regeln der Umsatzsteuer-Kategorien, die Reihenfolge der Daten und ob die Summen aufgehen - und dass alle Regeln ein Validator prüft.
* **Anlagen** - unter der Rechnung steht jede Anlage mit Name, Typ und Größe. PDFs und Bilder lassen sich dort ansehen, und eine Anlage eines der sechs erlaubten Typen lässt sich speichern.
* **Business-Term-Beschriftungen mit kurzer Beschreibung** - jedes Feld kann seinen BT-/BG-Code tragen, und beim Überfahren erscheinen die Bezeichnung des Terms sowie eine kurze Beschreibung des Feldes auf Deutsch, Englisch oder Französisch. Weitere Angaben (Kardinalität, Datentyp, Geschäftsregeln) liefert die separate Erweiterung "eInvoice Viewer - Technical Details". Live umschaltbar, per Einstellung oder über das Shortcode-Attribut `bt="1"` / `bt="0"`.
* **Dreisprachig** - die Oberfläche steht auf Englisch, Deutsch und Französisch zur Verfügung.
* **Themeunabhängig** - die Darstellung läuft in einem Shadow DOM mit vollständigem Style-Reset, sodass keine Theme-Styles durchschlagen.
* **Liest sich wie eine Rechnung** - ein Belegkopf mit dem Zahlbetrag, jede Business Group in einem Block mit ihrem Namen als Fahne auf dem Rahmen, die Beschriftung neben dem Wert und in einem breiten Block zwei Feldzeilen nebeneinander. Das Layout richtet sich nach der Spalte, in der es steht, nicht nach dem Fenster. Die Schriften liegen im Plugin; nichts wird von einem Schriftendienst geladen.
* **Das Dokument verlässt nie Ihren Browser** - Lesen und Darstellen geschehen beide im Browser; Ihr Server erhält die Datei nie, sodass es von vornherein nichts zu speichern, zu protokollieren oder preiszugeben gibt.
* **Sicher von Grund auf** - die Rechnung wird vom `DOMParser` des Browsers selbst gelesen (kein serverseitiger XML-Parser, also keine XXE-Angriffsfläche durch den Upload selbst); jeder Rechnungswert wird bei der Ausgabe maskiert. Verwandte kostenpflichtige Erweiterungen, die tatsächlich serverseitig arbeiten müssen (Validierung, Export, …), behalten ihre eigene, unabhängige Upload-, Nonce- und Rate-Limit-Behandlung.
* **Erweiterbar** - ein Add-on kann das Rechnungsmodell und die BT-/BG-Tooltips über zwei Haken an der Viewer-Komponente erweitern, `extendModel` und `extendTooltip`, und ein Ereignis `einvoice-model-ready` reicht ihm das fertige Modell, das rohe XML und die Originaldatei.

= Bibliotheken von Drittanbietern =

**Mozilla pdf.js** (`assets/js/pdfjs/`, Apache License 2.0, Lizenztext liegt daneben) ist der einzige mitgelieferte Fremdcode; es holt das eingebettete XML aus einer ZUGFeRD-/Factur-X-PDF. Alles Übrige im Plugin ist eigener Code dieser Plugin-Familie unter GPL-2.0-or-later.

== Demo ==

Probieren Sie den Viewer aus, ohne etwas zu installieren: [Live-Demo auf invoicePress.de](https://invoicepress.de/en/solutions/einvoice-tools/einvoice-viewer-for-peppol-en16931/einvoice-viewer-for-peppol-en16931-demo/). Ziehen Sie Ihre eigene E-Rechnung hinein; sie wird in Ihrem Browser gelesen und dargestellt, genau wie auf Ihrer eigenen Website. Das Plugin selbst ruft die Demo-Seite nie auf.

== Installation ==

1. Laden Sie den Plugin-Ordner nach `/wp-content/plugins/` hoch oder installieren Sie das Plugin über die WordPress-Plugin-Verwaltung.
2. Aktivieren Sie das Plugin in der Plugin-Verwaltung von WordPress.
3. Fügen Sie den Shortcode `[spev_einvoice_viewer]` in eine beliebige Seite oder einen Beitrag ein. Um die EN-16931-Business-Term-Nummern von Anfang an anzuzeigen, verwenden Sie `[spev_einvoice_viewer bt="1"]`.
4. Passen Sie bei Bedarf die Optionen unter **Einstellungen -> E-Rechnungs-Viewer** an (maximale Dateigröße, Standardsichtbarkeit der BT-Nummern, Design-Kit). Das Design-Kit gilt für alle eInvoice-Werkzeuge der Website.
5. Das vollständige Handbuch liegt dem Plugin bei, im Ordner `manual/` des Plugins ( `manual/manual.de.html`, englisch `manual/manual.en.html` ).

== Frequently Asked Questions ==

= Wird die hochgeladene Rechnung irgendwo gespeichert? =

Nein. Die Rechnung wird vollständig im Browser des Besuchers gelesen und dargestellt - sie wird nie an Ihren Server gesendet, es gibt also nichts zu speichern, zu protokollieren oder zu löschen. Weder im Upload-Verzeichnis noch in der Datenbank wird etwas abgelegt. (Kostenpflichtige Schwester-Erweiterungen, die serverseitige Verarbeitung wirklich brauchen - etwa Validierung oder Export -, laden selbst hoch und weisen darauf hin.)

= Welche Rechnungsformate werden unterstützt? =

ZUGFeRD- und Factur-X-PDFs (das eingebettete XML wird extrahiert) sowie CII-XML (UN/CEFACT) und UBL-/XRechnung-XML.

= Prüft der Viewer, ob eine Rechnung gültig ist? =

Nein. Der Viewer zeigt, was in der Rechnung steht, und er markiert Beträge, die nicht aus ihren Teilen aufgehen, und Felder, die eine Regel aus der Rechnung selbst verletzen. Ob eine Rechnung den Regeln ihrer Spezifikation entspricht, beurteilt ein Validator. Ein PDF ohne eingebettete Rechnungsdaten, etwa ein Scan, kann der Viewer nicht lesen, und er sagt das.

= Warum sieht die Ansicht nicht pixelgenau so aus wie im Quba-Viewer? =

Der Quba-Viewer wandelt die Rechnung mit dem KoSIT-XRechnung-XSLT 3.0 über Saxon-JS um. Dieses Plugin baut seine Ansicht stattdessen selbst: es liest die Rechnung direkt, setzt daraus ein EN-16931-Modell zusammen und stellt dieses Modell dar. Das Ergebnis ist an Quba angelehnt und funktional gleichwertig, aber es ist nicht das identische KoSIT-Markup. Der eigene Weg hat den Vorteil, dass er CII und UBL jeweils auf ihren eigenen Begriffen liest und jedes Feld mit seiner Business-Term-Nummer beschriften kann.

= Kann ich begrenzen, wie viele Rechnungen ein Besucher hochladen darf? =

Es gibt nichts zu begrenzen: die Rechnung erreicht Ihren Server nie. Sie wird im Browser des Besuchers gelesen und dargestellt, es entsteht also keine Anfrage und es wird keine Ressource von Ihnen verbraucht, wie viele Rechnungen ein Besucher auch ansieht. Die einzige Einstellung, die für die Datei gilt, ist die maximale Größe unter Einstellungen -> E-Rechnungs-Viewer; eine größere Datei wird abgewiesen, bevor sie gelesen wird.

Kostenpflichtige Schwester-Erweiterungen, die die Rechnung tatsächlich an Ihren Server senden (KoSIT-Prüfung, Excel-Export, Visualizer), bringen jeweils ihr eigenes Tageslimit je Besucher mit, einstellbar auf derselben Seite.

== Screenshots ==

1. Der Drag-&-Drop-Uploader, dargestellt durch den Shortcode `[spev_einvoice_viewer]`.
2. Eine dargestellte Rechnung im strukturierten, an Quba angelehnten Layout.
3. Dieselbe Rechnung mit eingeblendeten EN-16931-Business-Term-Nummern.
4. Die Einstellungsseite unter Einstellungen -> E-Rechnungs-Viewer.
5. Eine Rechnung mit fehlenden Pflichtfeldern: Jedes Feld ist rot markiert, und darunter steht die Regel.
6. Ein Betrag, der nicht aufgeht: Unter dem markierten Feld stehen der angegebene und der errechnete Betrag.
7. Die Anlagen unter der Rechnung, mit einer geöffneten PDF-Anlage.

== Changelog ==

= 2.37.0 =
* Der Viewer folgt dem in den Einstellungen gewählten Design-Kit ( Kit H, Kit A, Kit S oder ein dort hochgeladenes eigenes Kit ): Knöpfe, Sprachumschalter, Meldungen und der Einstellungsdialog nehmen ihr Aussehen daraus.
* Die Pluginliste verweist auf das Handbuch und auf den Support.
* Gescannte PDF-Anlagen ( schwarz-weisse Scans, wie viele Scanner und Faxprogramme sie erzeugen ) erscheinen in der Vorschau wieder mit Inhalt statt als leere Seiten.
* Die Vorschau einer Anlage nutzt die volle Breite der Seite.
* Die Beschriftung „Layout“ steht vor ihrer Auswahl, und die Knöpfe über der Rechnung brauchen eine Zeile weniger.
* Die Knöpfe „Mit VAMPIRE prüfen“ und „Mit Mustang prüfen“ funktionieren für eine ZUGFeRD- oder Factur-X-PDF; vorher antwortete der Validator „Bitte wählen Sie eine XML-Datei.“
* Die Karten eines Abschnitts stehen in zwei Spalten und in einer schmalen Spalte untereinander, auch wenn der Viewer in einem schmalen Teil der Seite steht; die Schalter über der Rechnung sehen aus wie der Rest des Design-Kits.

= 2.36.0 =
* Die Zeile über der Rechnung nennt die Fassung des Formats, wenn die Rechnung sie angibt, zum Beispiel „XRechnung-CII 3.0“.
* Neuer Knopf „Einstellungen“ über der Ablagefläche, sobald eine Erweiterung mit Einstellungen aktiv ist. Die Einstellungen liegen auf der Website, und jede Erweiterung legt fest, wer sie ansehen und wer sie ändern darf; eine Sicherungsdatei speichert und lädt sie.
* Erweiterungen können neben der Feldnummer eine zweite kleine Plakette zeigen, zum Beispiel den Namen der Referenzliste, aus der ein Code stammt.
* Neue Gestaltung „Kit S“ auf der Einstellungsseite: der Viewer in den Farben der specialPress-Websites.

= 2.35.0 =
* Die Werkzeugleiste hat zwei Zeilen: oben die Schalter und die Knöpfe der Erweiterungen, darunter Feldbrowser, Regelbrowser und „Zurücksetzen“.
* Ist Extended Profiles aktiv, blendet der neue Schalter „Nur gefüllte BT-X-Felder“ leere Erweiterungsfelder aus; er ist vorab eingeschaltet.
* Neuer Abschnitt „Zugang und Grenzen“ auf der Einstellungsseite: eine tägliche Zahl an Rechnungen je Rolle und für jede Erweiterung die Rolle, ab der sie zur Verfügung steht.
* Sind die Validatoren für Mustang und VAMPIRE aktiv, zeigt der Viewer für jeden einen eigenen Knopf neben dem KoSIT-Knopf.

= 2.34.1 =
* Geändert: Mit dem Zusatz Identifier Check heisst der Knopf in der Werkzeugleiste jetzt „Kennungen online prüfen“ und erscheint nur bei eingeschalteter Online-Prüfung; der Zusatz markiert falsche Kennungen direkt an ihren Feldern.
* Behoben: Mit aktivem Visualizer bleibt „Zurücksetzen“ in der Werkzeugleiste oben rechts, und die Auswahl des Layouts hat dieselbe Höhe und dasselbe Aussehen wie die Knöpfe daneben, auch unter Themes wie Enfold.

= 2.34.0 =
* Die Prüfziffer einer IBAN prüft nicht mehr der Viewer, sondern der Zusatz Identifier Check, zusammen mit den übrigen Kennungen.
* Eine UBL-Gutschrift zeigt jetzt ihr Fälligkeitsdatum und ihre Projektreferenz.

= 2.33.1 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.33.0 =
* Jedes Bankkonto und jedes Zahlungsmittel der Rechnung wird angezeigt, nicht nur das erste.

= 2.32.0 =
* Der Hinweis über den markierten Feldern sagt jetzt genau, was der Viewer prüft.
* Die mitgelieferten Übersetzungen erscheinen jetzt auch bei netzwerkweiter Aktivierung und unter älteren WordPress-Fassungen.

= 2.31.0 =
* Bei einer UBL-Rechnung erscheinen jetzt Felder, die bisher immer leer blieben, darunter die Spezifikationskennung, der Zahlungsempfänger, der Steuerbevollmächtigte des Verkäufers, das Datum der Steuerfälligkeit, die Gläubiger-Kennung und die Bemerkungen zur Rechnung.
* Der Geschäftsprozesstyp der Rechnung wird jetzt angezeigt, bei UBL und bei CII.
* Die Daten einer UBL-Rechnung stehen in der Schreibweise der Seitensprache.
* Ein Bruttopreis, eine Basismenge, der Grundbetrag oder Prozentsatz eines Zu- oder Abschlags oder ein Steuersatz, den die Rechnung nicht nennt, bleibt leer, statt 0 zu zeigen.
* Mit dem Zusatz Extended Profiles ab Fassung 1.6.0 haben auch die Felder der XRechnung Extension und von EXTENDED-CTC-FR einen Namen im Tooltip.

= 2.30.0 =
* Verletzt ein Feld eine Regel, die aus der Rechnung selbst folgt, etwa ein fehlendes Pflichtfeld, eine Regel seiner Umsatzsteuerkategorie oder ein Fälligkeitsdatum vor dem Rechnungsdatum, ist das Feld rot markiert und trägt einen Hinweis, und ein Kasten über der Rechnung sagt, dass das Hinweise und keine vollständige Prüfung sind.
* Die Knöpfe der Zusätze und die Layout-Auswahl des Visualizers folgen dem Design-Kit, und die Layout-Auswahl steht in einer Zeile mit ihren beiden Knöpfen.
* Auf einer Seite mit Viewer und Feld- oder Regelbrowser sind beide übersetzt.

= 2.29.0 =
* Vergabenummer und Objektkennung stehen jetzt mit dem Schema der Objektkennung unter den Bestell- und Vertragsdaten und nicht mehr als rechnungsbegründende Unterlagen.
* Der Steuerbetrag in Buchungswährung wird an seiner Währung erkannt und nicht mehr an seiner Stellung, und der Rechenhinweis unter dem Gesamtbetrag nennt den richtigen Betrag.
* Die Belegansicht kann eine Rechnung zeigen, die das Plugin „eInvoice Inbound“ schon geprüft hat, mit Feldnamen in den Tooltips, Anlagen und dem Original-PDF.

= 2.28.1 =
* Mit aktivem Zusatz Extended Profiles erscheinen die Karten der Erweiterung in der Sprache der Seite.

= 2.28.0 =
* Mit aktivem Zusatz Extended Profiles tragen die Felder der Erweiterung einer EXTENDED-Rechnung ihren Namen im Tooltip.

= 2.27.0 =
* Geändert: Die Karten „Rechnungsbegründende Unterlagen“ innerhalb der Rechnung entfallen. Dieselben Anlagen stehen jetzt nur noch einmal, im Abschnitt „Anlagen“ unter der Rechnung.

= 2.26.0 =
* Neu: Das Design-Kit des Viewers lässt sich unter Einstellungen → E-Rechnungs-Viewer wählen. Die Auswahl gilt für alle eInvoice-Werkzeuge der Website, Vorgabe bleibt Kit H.
* Neu: Unter der Rechnung führt ein Abschnitt „Anlagen“ jede Anlage mit Name, Typ und Größe auf. PDFs und Bilder lassen sich dort ansehen, und jede Anlage eines erlaubten Typs lässt sich speichern.

= 2.25.0 =
* Geht ein Betrag der Rechnung nicht aus seinen Teilen auf, ist das Feld rot markiert, und darunter stehen der ausgewiesene und der nachgerechnete Betrag.

= 2.24.5 =
* Summen, die die Rechnung nicht nennt, erscheinen jetzt leer statt mit 0,00.
* Die deutschen Bezeichnungen einiger Felder sind überarbeitet, zum Beispiel „Referenz des Käufers“ statt „Käufer Referenz / Leitweg-ID“.

= 2.24.4 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.24.3 =
* Behoben: Positionsgruppen hiessen in der deutschen und französischen Ansicht „Item 2“; sie heissen jetzt „Position 2“ und „Ligne 2“.

= 2.24.2 =
* Abstände und Schriftgrössen der Belegansicht entsprechen wieder der Fassung 2.18.0.

= 2.24.1 =
* Der graue Grund hinter dem ganzen Werkzeug ist entfernt; der Beleg steht auf dem Grund der Seite.
* Ein Block über die volle Breite zeigt wieder zwei Feldzeilen nebeneinander.

= 2.24.0 =
* Neue Gestaltung der Belegansicht: eine dunkle Fahne als Blocküberschrift, die Beschriftung neben dem Wert, ein hellblaues Wertfeld und ein gelber Haftungshinweis.
* Der Schalter für nur gefüllte Felder blendet eine Untergruppe zusammen mit ihrer Überschrift aus.

= 2.23.2 =
* Blocküberschriften stehen wieder in der Titelschrift.

= 2.23.1 =
* Behoben: Im Belegkopf fehlte das Leerzeichen zwischen Beschriftung und Wert ( „Rechnungsdatum11.02.2026“ ).
* Der Haftungshinweis steht als Hinweis über dem Beleg.
* Der Summenblock läuft über die volle Breite.

= 2.23.0 =
* Werkzeugleiste, Ablagefläche und Knöpfe tragen die Farben der übrigen E-Rechnungs-Werkzeuge.

= 2.22.0 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.21.1 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.21.0 =
* Behoben: 2.20.0 blendete die Bereiche für Zu- und Abschläge aus, wenn Rechnung oder Position keine tragen. Jede Business Group wird wieder gezeigt; ausblenden kann nur der Schalter für nur gefüllte Felder.
* Behoben: Den Summen fehlten die Zeilen für Zu- und Abschläge, Vorauszahlung und Rundung, wenn ihr Wert null war.

= 2.20.0 =
* Die Zu- und Abschläge einer Position stehen in einer Tabelle über die volle Breite.

= 2.19.1 =
* Behoben: Auf einer Website, die den Viewer ohne seine Begleit-Plugins betreibt, endete jede Seite mit dem Viewer in einem schweren Fehler.

= 2.19.0 =
* Zahlen und Datumsangaben folgen der gewählten Sprache: Deutsch schreibt 5.900,00 EUR und 01.12.2017, Englisch 5,900.00 EUR und 2017-12-01, Französisch 5 900,00 EUR.

= 2.18.0 =
* Der Shortcode heisst jetzt `[spev_einvoice_viewer]`. **Eine Seite, die noch `[einvoice_viewer]` trägt, zeigt nichts** — bitte den Aktualisierungshinweis beachten.

= 2.17.7 =
* Behoben: Eine Seite, die den Viewer zweimal lädt, blieb leer.

= 2.17.6 =
* Meldungszeile, Schalter und Herkunftszeile stehen bündig mit der Ablagefläche.

= 2.17.5 =
* Die Belegansicht richtet sich nach der Breite ihres Behälters, nicht nach dem Browserfenster. In einer schmalen Spalte stehen die Blöcke untereinander, und unter 420 Pixeln steht jede Beschriftung über ihrem Wert.

= 2.17.4 =
* Behoben: Eine im eigenständigen Viewer gewählte Sprache änderte die Sprache einer WordPress-Seite auf derselben Website.

= 2.17.3 =
* Eine mit dem Umschalter des eigenständigen Viewers gewählte Sprache bleibt gespeichert.
* Die Formate PDF und XML sind im Hinweissatz der Ablagefläche hervorgehoben.
* Keine runden Ecken mehr, wie in den übrigen E-Rechnungs-Werkzeugen.

= 2.17.1 =
* Behoben: Die Ablagefläche zeigt die Formate PDF und XML wieder.
* Entfernt: die Einstellungen „Uploads pro Besucher und Tag“ und „Proxy-Header vertrauen“. Sie wirkten seit 2.12.0 nicht mehr, weil die Rechnung den Server nicht mehr erreicht. Ein gespeicherter Wert bleibt ungenutzt in der Datenbank.

= 2.17.0 =
* Behoben: Nach einem Sprachwechsel konnte ein Add-on wie die Eingangsprüfung, der Excel-Export oder der Visualizer mit dem Beleg in der vorigen Sprache arbeiten.
* Behoben: „Zurücksetzen“ behielt die hochgeladene Rechnung im Speicher.
* Behoben: Die maximale Dateigrösse wird wieder durchgesetzt.
* Entfernt: die Einstellungen „Uploads pro Besucher und Tag“, „Proxy-Header vertrauen“ und „Layout-Vorlage“, die seit 2.12.0 nicht mehr wirkten. Das Layout wählt das Add-on Visualizer.

= 2.16.3 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.16.2 =
* Behoben: Die Felder „Globale Kennung“ (BT-71-0) und „Schema der Kennung“ (BT-71-1) verschwanden, wenn sie leer waren, während jedes andere leere Feld mit einem Strich erschien. Dasselbe galt für Verkäufer, Käufer und Zahlungsempfänger.

= 2.16.1 =
* Ein ZUGFeRD-/Factur-X-PDF wird schneller und mit weniger Code gelesen.
* Behoben: Das einbettbare Element `<einvoice-viewer>` beachtete sein Attribut `data-lang` für Ablagefläche und Schalter nicht.

= 2.16.0 =
* Behoben: Auf einer deutschen oder französischen Website erschienen Feldbeschriftungen, Codebezeichnungen, Umsatzsteueraufteilung, Zu- und Abschläge und Summen auf Englisch.
* Behoben: Drei Beschriftungen im Belegkopf blieben englisch.
* Behoben: 72 Texte, darunter die Knöpfe der Werkzeugleiste und alle Fortschritts- und Fehlermeldungen, waren nie übersetzt.

= 2.14.11 =
* Der Viewer folgt der Gestaltung der übrigen E-Rechnungs-Werkzeuge: kantig, Linien statt Schatten, Schreibmaschinenschrift für Kennungen.

= 2.14.10 =
* Der Download des Plugins ist kleiner; die Tooltips sind unverändert.
* Behoben: Die FAQ beschrieb die Rechnung noch als auf den Server hochgeladen.

= 2.14.9 =
* Behoben: Die zusätzlichen Tooltip-Angaben des Add-ons Technical Details — Kardinalität, Datentyp, anwendbare Regeln, XSD-Ebene, Referenztabelle, CII-/UBL-Pfad — erschienen nicht mehr.

= 2.14.8 =
* Behoben: Trug eine Partei sowohl eine einfache als auch eine globale Kennung, fiel die einfache weg, und ihr Wert stand unter dem falschen Business Term.

= 2.14.7 =
* Behoben: Auf manchen Servern scheiterte das Hochladen eines PDF mit „Failed to fetch dynamically imported module“.

= 2.14.6 =
* Die PDF-Bibliothek liegt dem Plugin bei, statt von einem fremden Server geladen zu werden.

= 2.14.5 =
* Behoben: Das Hochladen eines PDF scheiterte weiterhin mit „Das PDF enthält keine lesbare E-Rechnung“.

= 2.14.4 =
* Die Akzentfarbe ist das Marineblau der übrigen E-Rechnungs-Werkzeuge.

= 2.14.3 =
* Behoben: Das Hochladen eines PDF scheiterte mit „PDF reading needs pdf.js, which this page has not loaded.“
* Behoben: Mehrere Felder einer UBL-/PEPPOL-BIS-Rechnung — Kennungsschemata, Einheitencodes, Bezeichnung der Zahlungsart, Klassifizierungscodes der Artikel — waren immer leer.
* Behoben: Die Lieferangaben einer UBL-/PEPPOL-BIS-Rechnung waren immer leer.

= 2.14.2 =
* Behoben: Mit manchen Themes zeigte der Shortcode einen leeren Kasten.

= 2.14.1 =
* Der Plugin-Kopf nennt die nötige PHP-Fassung.

= 2.12.4 =
* Behoben: Die Felder des Add-ons Extended Profiles erschienen nicht mehr in der Belegansicht.

= 2.12.3 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.12.2 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.12.1 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.12.0 =
* **Die Rechnung wird vollständig im Browser gelesen und angezeigt und erreicht den Server nicht.** Das tägliche Upload-Limit dieses Plugins entfällt; die Add-ons, die eine Rechnung an den Server senden, behalten ihr eigenes.
* Die Tooltips zu den Business Terms erscheinen in der Sprache des Besuchers.

= 2.10.10 =
* Die Ablagefläche ist im Viewer, im Validator und im Explainer gleich breit.

= 2.10.9 =
* Behoben: Der Beleg nutzte nur die Breite der Ablagefläche statt der vollen verfügbaren Breite.

= 2.10.8 =
* Neue Knöpfe „Field Browser“ und „Rule Browser“, wenn das Add-on Technical Details aktiv ist.

= 2.10.7 =
* Für Entwickler: neue Aktion `spev_register_settings`, mit der ein Add-on einen eigenen Block auf der Einstellungsseite anlegt.

= 2.10.4 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.10.3 =
* Behoben: Der Kontakt des Käufers konnte Name, Telefon und E-Mail des Verkäuferkontakts zeigen.

= 2.10.2 =
* Der Belegkopf zeigt keine BT-Nummern mehr; dieselben Felder tragen sie im Block darunter.
* In den Tabellen für Zu- und Abschläge und Umsatzsteuer steht die BT-Nummer über dem Wert.
* Die Überschrift der Tabelle für Zu- und Abschläge nennt beide Business Groups, BG-20 und BG-21.

= 2.10.1 =
* Behoben: Der Kontakt des Käufers zeigte Name, Telefon und E-Mail des Verkäuferkontakts, wenn der Käufer keinen hatte.
* Behoben: Die Käuferreferenz (BT-10) erschien zweimal.
* Behoben: Die weiteren rechtlichen Angaben des Verkäufers (BT-33) wurden nie gezeigt.

= 2.10.0 =
* Für Entwickler: neue Methode `SPEV_Renderer::model_json()` und neue Aktion `spev_register_templates`, die das Add-on Visualizer für seine Drucklayouts nutzt.

= 2.9.0 =
* Neue REST-Route `POST /wp-json/einvoice/v1/render`. Bestehende Einstellungen bleiben erhalten.

= 2.8.0 =
* Wahlweiser Knopf für den Excel-Export, wenn das Add-on Exporter aktiv ist.

= 2.7.0 =
* Wahlweiser Knopf „Mit KoSIT prüfen“, wenn der eInvoice Validator aktiv ist; der Bericht erscheint auf derselben Seite.

= 2.6.1 =
* Zwei weitere codierte Felder zeigen ihre Bezeichnung statt eines nackten Codes: BT-21 und BT-128-0.

= 2.6.0 =
* Behoben: Ablagefläche und Meldungen beachteten die Sprache des Besuchers nicht.
* Codierte Werte erscheinen durchgehend als „Code - Bezeichnung“.
* Weitere codierte Felder zeigen ihre Bezeichnung statt eines nackten Codes.
* Behoben: Ein langer XPath konnte über seinen Tooltip hinauslaufen.

= 2.5.3 =
* Wartungsfassung ohne sichtbare Änderung.

= 2.5.2 =
* Behoben: Das Schema der Verkäufer- und Käuferkennung (BT-29-1, BT-46-1) blieb bei manchen Rechnungen leer.

= 2.5.0 =
* Für Entwickler: neuer Filter `spev_invoice_model`, mit dem ein Add-on der Rechnung Abschnitte hinzufügt.

= 2.4.0 =
* Für Entwickler: neuer Filter `spev_code_tooltip`, mit dem ein Add-on einen Tooltip zu einem Business Term erweitert.
* Behoben: Ein Tooltip konnte den Wortlaut einer anderen Rechnung zeigen.

= 2.3.0 =
* Schriften und Farben entsprechen dem Begleit-Plugin Validation Explainer.

= 2.2.0 =
* Einheitliche Blocküberschriften: Eine Gruppe innerhalb eines anderen Blocks trägt eine eigene, hellere Überschrift.
* Datum und Zahlen folgen der Sprache der Website, statt überall deutsch zu sein. Für Entwickler: neuer Filter `spev_date_format`.
* Behoben: Belegart, Zahlungsart, Einheit, Umsatzsteuerkategorie und Land waren immer deutsch.
* Behoben: Ein unbekannter Code der Belegart erschien als „Rechnung“; er erscheint jetzt als Code.
* Getestet mit WordPress 7.1.

= 2.1.0 =
* Vollständige Abdeckung von EN 16931: alle 164 Business Terms und alle 32 Business Groups, darunter Steuerbevollmächtigter und Zahlungsempfänger.
* Tooltips zu den Business Terms auf Deutsch, Englisch und Französisch, im Wortlaut der offiziellen Spezifikation.
* Französische Übersetzung der Oberfläche.
* Behoben: Jede Zuschlagszeile der Tabelle für Zu- und Abschläge war als Abschlag beschriftet.
* Behoben: Auf Windows-Servern scheiterte jedes Hochladen mit „Keine Datei empfangen“.
* Grundbeträge, Prozentsätze und Begründungscodes der Zu- und Abschläge, der Wert des Artikelattributs (BT-161) und die Positionskennung (BT-126) werden gezeigt.
* Der Download des Plugins ist deutlich kleiner.
* Für Entwickler: `SPEV_Renderer::model()` liefert die Rechnungsdaten statt HTML, und der Filter `spev_label_locale` wählt die Sprache der Business Terms.

= 2.0.1 =
* Business Terms korrigiert, geprüft gegen die offizielle Spezifikation EN 16931.
* Übersetzungen aktualisiert.
* Das Plugin heisst jetzt „invoicePress - e-Invoice Viewer“.

= 2.0.0 =
* Neues Layout mit farbigen Abschnittsfahnen und einem Block je Rechnungsposition.
* Viele weitere Felder, darunter BT-127 bis BT-160, Zahlungskarten und Lieferangaben.

== Upgrade Notice ==

= 2.31.0 =
Bei UBL-Rechnungen zeigt der Viewer jetzt Felder, die bisher immer leer blieben, etwa die Spezifikationskennung und die Bemerkungen. Für alle empfohlen.

= 2.18.0 =
HANDLUNG NÖTIG: Der Shortcode heisst jetzt [spev_einvoice_viewer]. Eine Seite, die noch [einvoice_viewer] trägt, zeigt nichts. Bitte jede Seite mit dem Viewer bearbeiten.

= 2.10.1 =
Behebt, dass der Kontakt des Käufers Telefon und E-Mail des Verkäufers zeigen konnte. Für alle empfohlen.

= 2.6.1 =
WICHTIG: braucht jetzt PHP 8.2.

= 2.2.0 =
Datum, Zahlen und codierte Werte folgen der Sprache der Website.

= 2.1.0 =
Vollständige Abdeckung von EN 16931, Tooltips in drei Sprachen, französische Oberfläche und eine Korrektur für das Hochladen auf Windows-Servern.
