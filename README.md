# Integración Virtuemart-Khipu

## Usar khipu como medio de pago

Esta extensión ofrece integración del sistema de e-commerce [Virtuemart](http://virtuemart.net/) con [khipu](https://khipu.com).
Al instalarlo permite a los clientes pagar usando *Transferencia simplificada* (usando el terminal de pago) o con *Transferencia electrónica normal*.

## Requisitos

Esta extensión es compatible con [Joomla](http://www.joomla.org/) 2.5.x/3.0.x y [Virtuemart](http://virtuemart.net/) 3.x. 

## Instalación

Puedes revisar una [guía online](https://khipu.com/page/virtuemart) de como instalar esta extensión.

Primero debes instalar esta extensión de virtuemart y crear un nuevo método de pago que use la extensión de khipu.

Luego debes ir a la configuración de la extensión y configurar las opciones básicas (montos máximos y mínimos para poder pagar usando khipu) y tus
credenciales de khipu, que incluyen tu *id de cobrador* y tu *llave de cobrador*. Estas las puedes obtener de
las opciones de tu cuenta de cobro en el portal de khipu.

## Como reportar problemas o ayudar al desarrollo

El sitio oficial de esta extensión es su [página en github.com](https://github.com/khipu/virtuemart-khipu). Si deseas informar de errores, revisar el 
código fuente o ayudarnos a mejorarla puedes usar el sistema de tickets y pull-requests. Toda ayuda es bienvenida.

## Empaquetar la extensión

Se incluye un script shell para empaquetar esta extensión y subirla a virtuemart. El script funciona sobre bash. Se debe ejecutar

$ ./build.sh

## Licencia GPL

Esta extensión se distribuye bajo los términos de la licencia GPL versión 3. Puedes leer el archivo license.txt con los detalles de la licencia.

