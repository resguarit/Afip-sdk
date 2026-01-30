<?php
/**
 * Factura A4 - medidas hoja A4 (210×297mm), márgenes 20mm.
 * Soporta Factura A (desglose IVA) y Factura B/C (IVA contenido - Ley 27.743)
 *
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $otros_tributos, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $tipo_codigo, $condicion_venta,
 *            $periodo_desde, $periodo_hasta, $fecha_vto_pago,
 *            $es_factura_a, $es_factura_b, $es_factura_c, $iva_contenido, $importe_neto_gravado, $iva_desglose
 */
$issuer = $issuer ?? [];
$receiver = $receiver ?? [];
$comprobante = $comprobante ?? [];
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$iva_total = $iva_total ?? 0;
$otros_tributos = $otros_tributos ?? 0;
$total = $total ?? 0;
$cae = $cae ?? '';
$cae_vencimiento = $cae_vencimiento ?? '';
$qr_src = $qr_src ?? '';
$tipo_letra = $tipo_letra ?? 'B';
$tipo_codigo = $tipo_codigo ?? 6;
$condicion_venta = $condicion_venta ?? 'Efectivo';
$fecha = $comprobante['fecha'] ?? '';
$periodo_desde = $periodo_desde ?? $fecha;
$periodo_hasta = $periodo_hasta ?? $fecha;
$fecha_vto_pago = $fecha_vto_pago ?? $fecha;
// Datos específicos por tipo de factura
$es_factura_a = $es_factura_a ?? false;
$es_factura_b = $es_factura_b ?? true;
$es_factura_c = $es_factura_c ?? false;
$iva_contenido = $iva_contenido ?? 0;
$importe_neto_gravado = $importe_neto_gravado ?? $subtotal;
$iva_desglose = $iva_desglose ?? [];
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Factura</title>
	<style type="text/css">
		@page {
			size: A4;
			margin: 20mm;
		}
		* { box-sizing: border-box; }
		body {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 12px;
			margin: 0;
			padding: 0;
			color: #000;
			width: 100%;
		}
		.bill-container {
			width: 100%;
			max-width: 170mm;
			margin-left: auto;
			margin-right: auto;
			border-collapse: collapse;
		}
		.bill-container td { vertical-align: top; }
		.bill-emitter-row td {
			border-bottom: 1px solid #000;
			padding: 6px 6px 6px 0;
		}
		.bill-emitter-row .col-emitter { width: 42%; text-align: left; font-size: 12px; }
		.bill-emitter-row .col-type { width: 16%; text-align: center; vertical-align: middle; }
		.bill-emitter-row .col-factura { width: 42%; text-align: left; padding-left: 8px; font-size: 12px; }
		.emitter-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
		.bill-type-wrap { margin-bottom: 2px; }
		.bill-type {
			width: 44px;
			height: 38px;
			margin: 0 auto 2px auto;
			border: 1px solid #000;
			background-color: #e5e5e5;
			color: #000;
			text-align: center;
			font-size: 28px;
			font-weight: 600;
			line-height: 38px;
			display: table;
		}
		.bill-type span { display: table-cell; vertical-align: middle; }
		.type-codigo { font-size: 11px; }
		.type-factura { font-size: 18px; font-weight: bold; }
		.text-right { text-align: right; }
		.bill-row td { padding: 0; border: 0; }
		.bill-row td > .inner {
			border-top: 1px solid #000;
			border-bottom: 1px solid #000;
			padding: 6px 0 8px 0;
		}
		.row-details .inner,
		.row-qrcode .inner { border: 0; padding: 6px 0 0 0; }
		.row-details table { border-collapse: collapse; width: 100%; }
		.row-details table td {
			border: 1px solid #000;
			padding: 6px 8px;
			font-size: 13px;
		}
		.row-details table tr:first-child td {
			background-color: #c0c0c0;
			font-weight: bold;
			text-align: center;
			color: #000;
		}
		.row-details table tr:not(:first-child) td { border-top: 1px solid #c0c0c0; }
		.row-details table td.num,
		.row-details table tr:first-child td:nth-child(n+3) { text-align: right; }
		.row-details table tr:not(:first-child) td:nth-child(1),
		.row-details table tr:not(:first-child) td:nth-child(2) { text-align: left; }
		.total-row .inner {
			border-top: 2px solid #000;
			border-bottom: 2px solid #000;
			padding: 6px 0 8px 0;
		}
		.totals-table { width: 100%; border-collapse: collapse; }
		.totals-table td { padding: 4px 0; border: 0; }
		.totals-table .label { text-align: right; padding-right: 10px; }
		.totals-table .amount { text-align: right; font-weight: bold; width: 100px; }
		.row-qrcode td { padding: 10px 10px 10px 0; }
		.row-qrcode td:last-child { text-align: right; vertical-align: top; }
		.qr-cell img { width: 150px; height: 150px; display: block; }
		.margin-b-10 { margin-bottom: 10px; }
		.period-row { width: 100%; border-collapse: collapse; }
		.period-row td { padding: 2px 0; }
		.period-row td:first-child { text-align: left; }
		.period-row td:nth-child(2) { text-align: center; }
		.period-row td:last-child { text-align: right; }
		.recipient-row { width: 100%; border-collapse: collapse; }
		.recipient-row td { padding: 2px 0; }
		.recipient-row .half { width: 50%; }
		.invoice-details { width: 100%; border-collapse: collapse; }
		.invoice-details td { padding: 1px 0; font-size: 12px; }
		.invoice-details .lbl { width: 1%; white-space: nowrap; padding-right: 6px; }
		.invoice-details .val { text-align: right; }
		.recipient-row .c1 { width: 38%; }
		.recipient-row .c2 { width: 62%; }
	</style>
</head>
<body>
	<table class="bill-container">
		<!-- Encabezado estilo AFIP: emisor (izq) | B + FACTURA + Cod (centro) | datos comprobante (der) -->
		<tr class="bill-emitter-row">
			<td class="col-emitter">
				<div class="emitter-name"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></div>
				<p><strong>Razón social:</strong> <?= htmlspecialchars($issuer['razon_social'] ?? '') ?></p>
				<p><strong>Domicilio Comercial:</strong> <?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
				<p><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($issuer['condicion_iva'] ?? 'Responsable inscripto') ?></p>
			</td>
			<td class="col-type">
				<div class="bill-type-wrap"><div class="bill-type"><span><?= htmlspecialchars($tipo_letra) ?></span></div></div>
				<div class="type-codigo">Cod. <?= str_pad((string) (int) $tipo_codigo, 3, '0', STR_PAD_LEFT) ?></div>
				<div class="type-factura">FACTURA</div>
			</td>
			<td class="col-factura">
				<table class="invoice-details">
					<tr>
						<td class="lbl"><strong>Punto de Venta:</strong></td>
						<td class="val"><?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></td>
					</tr>
					<tr>
						<td class="lbl"><strong>Comp. Nro:</strong></td>
						<td class="val"><?= htmlspecialchars($comprobante['nro'] ?? '') ?></td>
					</tr>
					<tr>
						<td class="lbl"><strong>Fecha de Emisión:</strong></td>
						<td class="val"><?= htmlspecialchars($comprobante['fecha'] ?? '') ?></td>
					</tr>
					<tr>
						<td class="lbl"><strong>CUIT:</strong></td>
						<td class="val"><?= htmlspecialchars($issuer['cuit'] ?? '') ?></td>
					</tr>
					<?php if (!empty($issuer['iibb'])): ?>
					<tr>
						<td class="lbl"><strong>Ingresos Brutos:</strong></td>
						<td class="val"><?= htmlspecialchars($issuer['iibb']) ?></td>
					</tr>
					<?php endif; ?>
					<?php if (!empty($issuer['inicio_actividad'])): ?>
					<tr>
						<td class="lbl"><strong>Fecha de Inicio de Actividades:</strong></td>
						<td class="val"><?= htmlspecialchars($issuer['inicio_actividad']) ?></td>
					</tr>
					<?php endif; ?>
				</table>
			</td>
		</tr>
		<!-- Período: Desde (izq) | Hasta (centro) | Fecha vto. pago (der) -->
		<tr class="bill-row">
			<td colspan="3">
				<div class="inner">
					<table class="period-row">
						<tr>
							<td style="width:33%;"><strong>Período Facturado Desde:</strong> <?= htmlspecialchars($periodo_desde) ?></td>
							<td style="width:34%;"><strong>Hasta:</strong> <?= htmlspecialchars($periodo_hasta) ?></td>
							<td style="width:33%;"><strong>Fecha de Vto. para el pago:</strong> <?= htmlspecialchars($fecha_vto_pago) ?></td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<!-- Receptor -->
		<tr class="bill-row">
			<td colspan="3">
				<div class="inner">
					<table class="recipient-row">
						<tr>
							<td class="c1"><strong>CUIL/CUIT:</strong></td>
							<td class="c2"><?= htmlspecialchars($receiver['nro_doc'] ?? '0') ?></td>
						</tr>
						<tr>
							<td class="c1"><strong>Apellido y Nombre / Razón social:</strong></td>
							<td class="c2"><?= htmlspecialchars($receiver['nombre'] ?? 'Consumidor Final') ?></td>
						</tr>
						<tr>
							<td class="half"><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($receiver['condicion_iva'] ?? 'Consumidor final') ?></td>
							<td class="half"><strong>Domicilio:</strong> <?= htmlspecialchars($receiver['domicilio'] ?? '-') ?></td>
						</tr>
						<tr>
							<td colspan="2"><strong>Condicion de venta:</strong> <?= htmlspecialchars($condicion_venta) ?></td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<!-- Tabla de ítems -->
		<tr class="bill-row row-details">
			<td colspan="3">
				<div class="inner">
					<?php if ($es_factura_a): ?>
					<!-- FACTURA A: Tabla con desglose de IVA por ítem -->
					<table>
						<tr>
							<td>Código</td>
							<td>Producto / Servicio</td>
							<td class="num">Cantidad</td>
							<td class="num">U. Medida</td>
							<td class="num">Precio Unit.</td>
							<td class="num">% Bonif.</td>
							<td class="num">Subtotal</td>
							<td class="num">Alícuota IVA</td>
							<td class="num">Subtotal c/IVA</td>
						</tr>
						<?php foreach ($items as $item):
							$cant = (float) ($item['cantidad_calc'] ?? $item['quantity'] ?? $item['cantidad'] ?? 1);
							$pu = (float) ($item['precio_unitario_calc'] ?? $item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
							$st = (float) ($item['subtotal_calc'] ?? $item['subtotal'] ?? ($cant * $pu));
							$alicuota = (float) ($item['alicuota_iva'] ?? $item['taxRate'] ?? $item['iva_pct'] ?? 21);
							$stConIva = (float) ($item['subtotal_con_iva'] ?? round($st * (1 + $alicuota / 100), 2));
						?>
						<tr>
							<td><?= htmlspecialchars($item['code'] ?? $item['codigo'] ?? '-') ?></td>
							<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
							<td class="num"><?= number_format($cant, 2, ',', '.') ?></td>
							<td class="num"><?= htmlspecialchars($item['unit'] ?? $item['unidad'] ?? 'unidades') ?></td>
							<td class="num"><?= number_format($pu, 2, ',', '.') ?></td>
							<td class="num">0,00</td>
							<td class="num"><?= number_format($st, 2, ',', '.') ?></td>
							<td class="num"><?= number_format($alicuota, 0) ?>%</td>
							<td class="num"><?= number_format($stConIva, 2, ',', '.') ?></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<?php else: ?>
					<!-- FACTURA B/C: Tabla simplificada (precios con IVA incluido) -->
					<table>
						<tr>
							<td>Código</td>
							<td>Producto / Servicio</td>
							<td class="num">Cantidad</td>
							<td class="num">U. Medida</td>
							<td class="num">Precio Unit.</td>
							<td class="num">% Bonif.</td>
							<td class="num">Imp. Bonif.</td>
							<td class="num">Subtotal</td>
						</tr>
						<?php foreach ($items as $item):
							$cant = (float) ($item['cantidad_calc'] ?? $item['quantity'] ?? $item['cantidad'] ?? 1);
							$pu = (float) ($item['precio_unitario_calc'] ?? $item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
							$st = (float) ($item['subtotal_calc'] ?? $item['subtotal'] ?? ($cant * $pu));
						?>
						<tr>
							<td><?= htmlspecialchars($item['code'] ?? $item['codigo'] ?? '-') ?></td>
							<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
							<td class="num"><?= number_format($cant, 2, ',', '.') ?></td>
							<td class="num"><?= htmlspecialchars($item['unit'] ?? $item['unidad'] ?? 'unidades') ?></td>
							<td class="num"><?= number_format($pu, 2, ',', '.') ?></td>
							<td class="num">0,00</td>
							<td class="num">0,00</td>
							<td class="num"><?= number_format($st, 2, ',', '.') ?></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<!-- Totales -->
		<tr class="bill-row total-row">
			<td colspan="3">
				<div class="inner">
					<?php if ($es_factura_a): ?>
					<!-- FACTURA A: Desglose de IVA por alícuota -->
					<table class="totals-table">
						<tr>
							<td class="label"><strong>Importe Neto Gravado: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $importe_neto_gravado, 2, ',', '.') ?></strong></td>
						</tr>
						<?php
						// Alícuotas estándar AFIP en orden descendente
						$alicuotasAfip = ['27.0' => '27%', '21.0' => '21%', '10.5' => '10,5%', '5.0' => '5%', '2.5' => '2,5%', '0.0' => '0%'];
						foreach ($alicuotasAfip as $key => $label):
							$valor = $iva_desglose[$key] ?? 0;
						?>
						<tr>
							<td class="label"><strong>IVA <?= $label ?>: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $valor, 2, ',', '.') ?></strong></td>
						</tr>
						<?php endforeach; ?>
						<tr>
							<td class="label"><strong>Importe Otros Tributos: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $otros_tributos, 2, ',', '.') ?></strong></td>
						</tr>
						<tr>
							<td class="label"><strong>Importe Total: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></td>
						</tr>
					</table>
					<?php else: ?>
					<!-- FACTURA B/C: IVA Contenido (Ley 27.743 - Régimen de Transparencia Fiscal) -->
					<table class="totals-table">
						<tr>
							<td class="label"><strong>Subtotal: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></td>
						</tr>
						<?php if ($otros_tributos > 0): ?>
						<tr>
							<td class="label"><strong>Importe Otros Tributos: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $otros_tributos, 2, ',', '.') ?></strong></td>
						</tr>
						<?php endif; ?>
						<tr>
							<td class="label"><strong>Importe Total: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></td>
						</tr>
						<tr>
							<td colspan="2" style="padding-top: 10px; border-top: 1px solid #ccc;">
								<div style="font-size: 10px; color: #444;">
									<strong>Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)</strong><br>
									IVA Contenido: $ <?= number_format((float) $iva_contenido, 2, ',', '.') ?>
								</div>
							</td>
						</tr>
					</table>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<!-- QR (izq) | CAE (der) -->
		<tr class="bill-row row-qrcode">
			<td class="qr-cell">
				<?php if ($qr_src !== ''): ?>
				<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" width="150" height="150" />
				<?php endif; ?>
			</td>
			<td></td>
			<td>
				<div class="text-right margin-b-10"><strong>CAE N°:&nbsp;</strong><?= htmlspecialchars($cae) ?></div>
				<div class="text-right"><strong>Fecha de Vto. de CAE:&nbsp;</strong><?= htmlspecialchars($cae_vencimiento) ?></div>
			</td>
		</tr>
	</table>
</body>
</html>
