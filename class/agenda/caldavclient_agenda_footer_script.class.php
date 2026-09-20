<?php
/* Copyright (C) 2025-2026  MATER Stéphane
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       class/agenda/caldavclient_agenda_footer_script.class.php
 * \ingroup    caldavclient
 * \brief      Script fin de page agenda natif (comm/action/index.php) — DOM / grille jour / week-end / tuiles.
 */

/**
 * Génère le bloc &lt;script&gt; injecté par le hook llxFooter.
 */
class CaldavclientAgendaFooterScript
{
	/**
	 * Filet de sécurité du calendrier : retire les tuiles marquées « automatique système ».
	 *
	 * Le filtre principal reste le SQL (hooks printFieldListFrom / printFieldListWhere).
	 * Ce bloc ne sert que si Dolibarr a tout de même chargé l'événement — cas rencontré
	 * depuis la 24.0, où la requête du calendrier ne tient plus compte du JOIN ajouté.
	 * Le marqueur est posé par le hook eventOptions (ActionsCaldavclient::eventOptions).
	 *
	 * @return string Code JS (indenté d'une tabulation, à insérer dans jQuery(function () { … }))
	 */
	private static function buildHideSystemAutoSnippet()
	{
		return '	jQuery(".caldavclient-hide-systemauto-flag").each(function () {
		var flag = jQuery(this);
		var tuile = flag.closest("div.event");
		if (!tuile.length) {
			tuile = flag.closest("table.cal_event");
		}
		if (tuile.length) {
			tuile.remove();
		} else {
			flag.remove();
		}
	});
';
	}

	/**
	 * @param  string $nonceattr        Ex. ' nonce="..."' ou vide (déjà échappé pour attribut HTML)
	 * @param  int    $agendaStartWeek  MAIN_START_WEEK (0 = dimanche, 1 = lundi, …)
	 * @param  string $labelAlldayJs    Libellé « journée entière » déjà passé dans dol_escape_js()
	 * @param  bool   $hideSystemAuto   Option « masquer les automatiques système » active
	 * @return string                   HTML script complet
	 */
	public static function buildLlxFooterScript($nonceattr, $agendaStartWeek, $labelAlldayJs, $hideSystemAuto = false)
	{
		$sw = (int) $agendaStartWeek;
		$purge_systemauto = $hideSystemAuto ? self::buildHideSystemAutoSnippet() : '';

		return '<script type="text/javascript"'.$nonceattr.'>
jQuery(function () {
'.$purge_systemauto.'	var form = jQuery("#searchFormList");
	if (form.length && form.find(".sectioncalendarbymonth, .sectioncalendarbyweek, .sectioncalendarbyday").length) {
		var barre = form.find("table.table-fiche-title").first();
		if (barre.length) {
			barre.nextUntil("div.liste_titre.liste_titre_bydiv").remove();
		}
	}
	// Vue mois : fusion samedi + dimanche si les deux colonnes sont adjacentes (semaine type FR : lun. → dim.).
	// Sinon : même couleur week-end sur les deux colonnes (ex. semaine commençant le dimanche).
	var caldavStartWeek = '.$sw.';
	var monthGrid = jQuery(".sectioncalendarbymonth.maxscreenheightless300 .cal_pannel.cal_month").first();
	if (monthGrid.length && !monthGrid.data("caldavWeekendDone")) {
		monthGrid.data("caldavWeekendDone", 1);
		var wk = [];
		var wi;
		for (wi = 0; wi < 7; wi++) {
			var numD = (wi + caldavStartWeek) % 7;
			if (numD === 0 || numD === 6) {
				wk.push(wi);
			}
		}
		if (wk.length === 2) {
			wk.sort(function (a, b) { return a - b; });
			var iA = wk[0], iB = wk[1];
			var adjacent = (iB - iA === 1);
			monthGrid.find("tr").each(function () {
				var tr = jQuery(this);
				var tds = tr.children("td");
				if (tds.length !== 8) {
					return;
				}
				var ix0 = 1 + iA, ix1 = 1 + iB;
				var c0 = tds.eq(ix0), c1 = tds.eq(ix1);
				if (!c0.length || !c1.length) {
					return;
				}
				if (adjacent) {
					if (tr.hasClass("liste_titre")) {
						var lab0 = c0.text().replace(/\s+/g, " ").trim();
						var lab1 = c1.text().replace(/\s+/g, " ").trim();
						c0.text(lab0 + " – " + lab1);
						c0.addClass("caldavclient-weekend-merged caldavclient-weekend-header");
						c1.remove();
					} else {
						c0.attr("colspan", 2);
						jQuery.each((c1.attr("class") || "").split(/\s+/), function (_, cl) {
							if (cl) {
								c0.addClass(cl);
							}
						});
						c0.addClass("caldavclient-weekend-merged caldavclient-weekend-cell");
						c0.append(c1.contents());
						c1.remove();
					}
				} else {
					c0.addClass("caldavclient-weekend-cell caldavclient-weekend-split");
					c1.addClass("caldavclient-weekend-cell caldavclient-weekend-split");
					if (tr.hasClass("liste_titre")) {
						c0.addClass("caldavclient-weekend-header");
						c1.addClass("caldavclient-weekend-header");
					}
				}
			});
		}
	}
	// Vue mois : faire défiler pour centrer la semaine du jour actuel (cellule .cal_today), pas le haut du mois.
	var monthWrap = jQuery(".sectioncalendarbymonth.maxscreenheightless300");
	if (monthWrap.length) {
		var todayTd = monthWrap.find("td.cal_today").first();
		if (todayTd.length && todayTd[0].scrollIntoView) {
			todayTd[0].scrollIntoView({ block: "center", inline: "nearest", behavior: "auto" });
		}
	}
	// Vue jour : grille 24 h + ligne « journée entière » (réorganisation DOM uniquement, pas de modification du core).
	var daySection = jQuery(".sectioncalendarbyday.maxscreenheightless300");
	if (daySection.length) {
		var dayContainer = daySection.find(".div-table-responsive-no-min").first();
		var dayevent = dayContainer.find(".dayevent").first();
		if (dayevent.length && !dayContainer.data("caldavDaygrid")) {
			dayContainer.data("caldavDaygrid", 1);
			var agendacell = dayevent.find(".agendacell.sortable").first();
			if (!agendacell.length) {
				agendacell = dayevent.find(".agendacell").first();
			}
			if (agendacell.length) {
				var events = agendacell.children("div.event").detach();
				var allDayLabel = "'.$labelAlldayJs.'";
				var grid = jQuery("<table>").addClass("caldavclient-daygrid centpercent noborder").attr("role", "presentation");
				var tbody = jQuery("<tbody>");
				var adrow = jQuery("<tr>").addClass("caldavclient-daygrid-allday");
				adrow.append(jQuery("<th>").addClass("caldavclient-daygrid-hour nowrap").attr("scope", "row").text(allDayLabel));
				adrow.append(jQuery("<td>").addClass("caldavclient-daygrid-slot caldavclient-daygrid-slot-allday"));
				tbody.append(adrow);
				var h;
				for (h = 0; h < 24; h++) {
					var lbl = (h < 10 ? "0" : "") + h + ":00";
					var row = jQuery("<tr>").attr("data-hour-row", h);
					row.append(jQuery("<th>").addClass("caldavclient-daygrid-hour nowrap").attr("scope", "row").text(lbl));
					row.append(jQuery("<td>").addClass("caldavclient-daygrid-slot").attr("data-hour", h));
					tbody.append(row);
				}
				grid.append(tbody);
				agendacell.prepend(grid);
				function caldavclientParseStartEnd(ev$) {
					var txt = (ev$.find("td.cal_event").first().text() || ev$.text() || "").trim();
					if (/jour\s+complet|journée\s+entière|full\s*day|whole\s*day|toute\s*(la\s*)?journ|ganzt|ganzer\s*tag|eventonfullday/i.test(txt)) {
						return { allday: true };
					}
					function parseOneTime(m) {
						if (!m) { return null; }
						var hh = parseInt(m[1], 10);
						var mm = parseInt(m[2], 10);
						var ap = m[3] ? String(m[3]).replace(/\./g, "").toLowerCase() : "";
						if (ap === "pm" && hh < 12) { hh += 12; }
						if (ap === "am" && hh === 12) { hh = 0; }
						if (isNaN(hh) || isNaN(mm) || hh < 0 || hh > 23 || mm < 0 || mm > 59) { return null; }
						return { h: hh, m: mm };
					}

					// Cas 11:30 - 12:30 (ou 11h30 à 12h30)
					var rxRange = /(\d{1,2})[:hH.](\d{2})\s*([ap]\.?m\.?)?\s*(?:-|–|—|à|to)\s*(\d{1,2})[:hH.](\d{2})\s*([ap]\.?m\.?)?/i;
					var mRange = txt.match(rxRange);
					if (mRange) {
						var t0 = parseOneTime([mRange[0], mRange[1], mRange[2], mRange[3]]);
						var t1 = parseOneTime([mRange[0], mRange[4], mRange[5], mRange[6]]);
						if (t0 && t1) {
							var startMin = t0.h * 60 + t0.m;
							var endMin = t1.h * 60 + t1.m;
							// Si heure de fin < heure de début, on considère un passage de minuit et on borne.
							if (endMin <= startMin) { endMin = startMin + 60; }
							return { allday: false, startMin: startMin, endMin: endMin };
						}
					}

					// Cas 11:30 (sans fin) -> durée par défaut 60 min
					var mOne = txt.match(/(\d{1,2})[:hH.](\d{2})\s*([ap]\.?m\.?)?/i);
					if (mOne) {
						var t = parseOneTime(mOne);
						if (t) {
							startMin = t.h * 60 + t.m;
							endMin = startMin + 60;
							return { allday: false, startMin: startMin, endMin: endMin };
						}
					}

					// Fallback historique: "11" ou "11 - 12" -> 11:00-12:00
					var mHour = txt.match(/^(\d{1,2})(?:\s*-\s*(\d{1,2}))?(?=\s|[^\d:]|$)/);
					if (mHour) {
						var h0 = parseInt(mHour[1], 10);
						var h1 = mHour[2] ? parseInt(mHour[2], 10) : (h0 + 1);
						if (!isNaN(h0) && h0 >= 0 && h0 <= 23) {
							if (isNaN(h1) || h1 < 0 || h1 > 24) { h1 = h0 + 1; }
							startMin = h0 * 60;
							endMin = Math.min(24 * 60, Math.max(startMin + 30, h1 * 60));
							return { allday: false, startMin: startMin, endMin: endMin };
						}
					}

					return { allday: true };
				}

				function clamp(n, lo, hi) { return Math.max(lo, Math.min(hi, n)); }

				events.each(function () {
					var ev$ = jQuery(this);
					var info = caldavclientParseStartEnd(ev$);
					if (info && info.allday) {
						grid.find("td.caldavclient-daygrid-slot-allday").first().append(ev$);
						return;
					}

					var startMin = (info && typeof info.startMin === "number") ? info.startMin : 0;
					var endMin = (info && typeof info.endMin === "number") ? info.endMin : (startMin + 60);
					startMin = clamp(startMin, 0, 24 * 60);
					endMin = clamp(endMin, 0, 24 * 60);
					if (endMin <= startMin) { endMin = Math.min(24 * 60, startMin + 60); }

					var startHour = Math.floor(startMin / 60);
					var cell = grid.find("td.caldavclient-daygrid-slot[data-hour=\"" + startHour + "\"]").first();
					if (!cell.length) {
						grid.find("td.caldavclient-daygrid-slot-allday").first().append(ev$);
						return;
					}

					// Placement "à cheval" : position verticale + hauteur proportionnelles.
					// On place l evenement dans la cellule de départ et on le laisse déborder (CSS overflow visible).
					var cellH = cell.innerHeight();
					if (!cellH || cellH < 20) { cellH = 48; }
					var topPx = ((startMin % 60) / 60) * cellH;
					var durMin = (endMin - startMin);
					var heightPx = Math.max(18, (durMin / 60) * cellH);

					ev$.addClass("caldavclient-daygrid-abs-event");
					ev$.css({
						position: "absolute",
						left: 8,
						right: 8,
						top: Math.round(topPx),
						height: Math.round(heightPx),
						margin: 0
					});

					cell.append(ev$);
				});
			}
		}
	}
	function caldavclientHexToRgb(hex) {
		hex = String(hex || "").replace(/^#/, "");
		if (hex.length !== 6 || !/^[0-9a-fA-F]{6}$/.test(hex)) {
			return null;
		}
		return [parseInt(hex.substr(0, 2), 16), parseInt(hex.substr(2, 2), 16), parseInt(hex.substr(4, 2), 16)];
	}
	function caldavclientSolidTileBackground(hexWithHash) {
		var rgb = caldavclientHexToRgb(hexWithHash);
		if (!rgb) {
			return null;
		}
		var dark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
		var t = dark ? 0.42 : 0.28;
		var br, bgc, bb;
		if (dark) {
			br = 30;
			bgc = 41;
			bb = 59;
		} else {
			br = 255;
			bgc = 255;
			bb = 255;
		}
		return "rgb(" +
			Math.round(br * (1 - t) + rgb[0] * t) + "," +
			Math.round(bgc * (1 - t) + rgb[1] * t) + "," +
			Math.round(bb * (1 - t) + rgb[2] * t) + ")";
	}
	jQuery(".caldavclient-agenda-border-flag").each(function () {
		var el = jQuery(this);
		var c = el.attr("data-border");
		if (!c || c.length < 4) {
			el.remove();
			return;
		}
		var tbl = el.closest("table.cal_event");
		if (tbl.length && tbl[0].style) {
			var bg = caldavclientSolidTileBackground(c);
			if (bg) {
				tbl[0].style.setProperty("background", bg, "important");
				tbl[0].style.setProperty("background-color", bg, "important");
			}
			tbl[0].style.setProperty("border-left", "5px solid " + c, "important");
		}
		el.remove();
	});
});
</script>';
	}
}
