# File Upload Control

Formulářová komponenta pro upload souborů. Pro použití si ho poděďte a vytvořte šablonu, ta výchozí vám nejspíš nebude k ničemu.

Umožňuje odeslání souborů dvěma způsoby:

- Normálně formulářově.
- Ajaxově.

Při zpracování formuláře dostanete jako hodnotu prvku pole entit, které reprezentují soubory. Viz napojení na model.

API těch ajaxových signálů bylo psané na míru [jQuery File Upload Plugin](https://github.com/blueimp/jQuery-File-Upload), ale mělo by být použitelné i s jinými nahrávadly. Viz napojení na frontend.


## Kompatibilita

Testy prochází jak na Nette `@dev` (momentálně nette/nette@ae11ca1), tak i na `2.0.12`.

Testy nebudou procházet v PHP 5.3 - párkrát jsem použil `$this` v closuře a možná sem tam nějaké to zkrácené pole... Ale samotná komponenta by v PHP 5.3 fungovat měla.


## Napojení na Template factory

Komponenta vyžaduje, aby v service containeru byla služba s názvem "templateFactory" s public metodou `createTemplate($file, Nette\Application\UI\Control $control)`.

Je to trochu špína, ale správným řešením se teď nebudu zdržovat (správné řešení by mít pro tu službu rozhranní v dalším balíčku, který by tento balíček vyžadoval; a vůbec nejlepší by bylo, kdyby to rozhranní bylo už v Nette).


## Napojení na model

Konstruktoru této komponenty se předává objekt `IFilesRepository`, který je zodpovědný za CRUD entit typu `IFileEntity`. Mělo by to být nasaditelné jak na `PetrP/Orm`, tak i `Tharos/LeanMapper`.


## Napojení na frontend

Vlastností každého formulářového prvku v Nette je `htmlName`, od něj se odvozují názvy POST proměnných:

- `htmlName[]` - Nahrané soubory.
- `htmlName-autoUploaded[]` - ID souborů, které byly nahrané ajaxem. S tímto polem se pracuje pouze při odeslání formuláře.

Takže upload přes HTML obstará `<input type="file" name="{$control->htmlName}[]">` a případný ajaxový nahrávač také používá ten samý název (jen jinou URL, o tom dále).

Ajaxový nahrávač by měl po úspěšném ajaxovém uploadu do formuláře vložit `<input type="hidden" name="{$control->htmlName}-autoUploaded[]" value="...">`, kde ve `value` bude ID nahraného souboru (získá z payloadu upload signálu). Tím si formulář "pamatuje", jaké soubory se přes něj nahrály a po odeslání si jejich entity vytáhne z repository.

Tato komponenta se neomezuje pouze na nahrávání nových souborů, lze ji úspěšně využít i pro změnu pořadí stávajících souborů. Prostě je vypíšete a ke každému přihodíte ten `hidden` s `htmlName-autoUploaded[]`. Nahodíte jQuery sortable a máte hotovo. Při zpracování dostanete pole všech souborů ve správném pořadí.

### Ajaxový upload signál

`{var $pathToThisControl = $control->lookupPath('Nette\Application\UI\Presenter')}`
`{plink "$pathToThisControl-upload!"}`

Přijímá soubory v poli `htmlName[]`, vrací JSON payload v tomto formátu:

	{
		"files": [
			{
				"id": 1,
				"name": "nazev-souboru.txt",
				"size": 123,
				"type": "text/plain",
				"delete_type": "DELETE",
				"delete_url": "URL delete signálu",
				"thumbnail_url": "URL náhledu",
				"url": "URL plné velikosti"
			},
			{
				"id": 1,
				"name": "jini-soubor.gif",
				"size": 666,
				"type": "image/gif",
				"delete_type": "DELETE",
				"delete_url": "URL delete signálu",
				"thumbnail_url": "...",
				"url": "..."
			},
			{
				"name": "prilis-velky-soubor.txt",
				"error": "Maximální povolená velikost souboru je 123."
			},
			{
				"name": "vice-problemu.gif",
				"error": [
					"Je vyžadována přípona TXT.",
					"Maximální povolená velikost souboru je 123."
				]
			}
		]
	}

Hodnoty `thumbnail_url` a `url` jsou v odpovědi pouze v případě, že komponenta má definovaný IUrlProvider (viz `setUrlProvider`). Pokud by formát nevyhovoval, můžete předefinovat metody `createFilePayload()` a `createErrorPayload()`.

Při uploadu přes tento signál se provádí validace pouze podle těchto pravidel:

- `FilesUploadControl::MAX_FILE_SIZE`
- `FilesUploadControl::MIME_TYPE`
- `FilesUploadControl::IMAGE`
- `FilesUploadControl::RULE_EXTENSION`

Pokud jsou na controlu nasazené nějaké další validátory, tak na ty dojde až po odeslání formuláře.

### Ajaxový delete signál

`{var $pathToThisControl = $control->lookupPath('Nette\Application\UI\Presenter')}`
`{plink "$pathToThisControl-delete!"}`

Pokud má komponenta nastavenou session section (viz `setAutoUploadsSessionSection()`), tak tímto signálem lze smazat soubory (resp. zavolat `IFilesRepositor::deleteFile()`), které byly uploadovány přes upload signál, ale ještě nebyly zpracovány při odeslání formuláře.

Ale i pokud session nastavená není, nebo v ní není ten soubor, tak se zavolají události `onBeforeDelete` a `onDelete`, takže si to chování můžete i rozšířit. Do toho bych se ale nepouštěl, dokud se nějak uspokojivě nevyřeší CSRF (viz CSRF zranitelnost).

Bez ohledu na výsledek operace vrací tuto odpověď:

	{
		"success": true
	}


## Nastavení komponenty

Volitelné závislosti:

- `setUrlProvider(IFileUrlProvider $urlProvider)` - Pokud má upload signál vracet URL a URL náhledu, potřebuje vědět jak.
- `setAutoUploadsSessionSection(SessionSection $autoUploadsSession)` - Pokud chcete používat delete signál.

Události:

- `onAutoUpload(IFileEntity $file, \ArrayObject $filePayload)` - při upload signálu po uložení souboru. Může upravit payload (je to ArrayObject, protože s předáváním pole referencí jsem měl nějaké problémy, už nevím jaké).
- `onBeforeDelete(IFileEntity $file)` - před smazáním.
- `onDelete(IFileEntity $file)` - po smazání, i pokud neproběhlo. Tohle asi změním.


## CSRF zranitelnost

Upload ani delete signál nejsou ošetřené proti CSRF. Hrachův trait použít nelze, protože jednak chceme ještě zachovat kompatibilitu s PHP 5.3 a jednak tohle není `UI\Control`.

Je to řešitelné, ale nemá to prioritu.
