"""
XpertiFlow CRM V2 — WhatsApp Cloud API & CRM MCP Server
Permite al Agente de IA:
1. Consultar estado del número y de la WABA en Meta Graph API v22.0.
2. Ejecutar la inicialización criptográfica /register con PIN de 2 pasos.
3. Enviar mensajes de prueba salientes.
4. Sincronizar credenciales y estado en la base de datos de XpertiFlow CRM (Docker/PostgreSQL).
5. Listar canales y verificar salud del Webhook del CRM.
"""

import os
import sys
import json
import subprocess
import requests
from typing import Optional, Dict, Any, List
from mcp.server.mcpserver import MCPServer

# Constantes por defecto del proyecto XpertiFlow CRM
DEFAULT_PHONE_NUMBER_ID = os.getenv("WHATSAPP_PHONE_NUMBER_ID", "1379424651914337")
DEFAULT_WABA_ID = os.getenv("WHATSAPP_WABA_ID", "1841227566898143")
DEFAULT_ACCESS_TOKEN = os.getenv(
    "WHATSAPP_ACCESS_TOKEN",
    "EAANzxEhsjdwBSpV9tLovfV3AYumfGwJfwG0ZC5l2mvXtNiRcNmd4ZB6n8kDXdScG9TGZAUULp5LODUo3D1byl24gjR4F2cieXAl4PZAUcL01ETUzGKxx2xVk0MQe0TEYeI59Q6U5Iz6ZCqic8dVuxYo3hNDZBuNJmKUZA1VBPSeQECwMMyspwmyZBDwWpFgpCwZDZD"
)
DEFAULT_VERIFY_TOKEN = os.getenv("WHATSAPP_VERIFY_TOKEN", "dZBqXOQw2jcvLjeTemrmf0b9OGmDKSQ8")
DEFAULT_ORG_ID = os.getenv("CRM_ORGANIZATION_ID", "01m48mb83hmc0rn72qhfkj999k")
GRAPH_API_VERSION = "v22.0"

# Inicializar servidor MCP
server = MCPServer("xpertiflow-whatsapp-crm")


def _get_token(token: Optional[str]) -> str:
    return (token or DEFAULT_ACCESS_TOKEN).strip()


@server.tool()
def whatsapp_check_phone_status(
    phone_number_id: str = DEFAULT_PHONE_NUMBER_ID,
    access_token: str = ""
) -> Dict[str, Any]:
    """
    Consulta en tiempo real a Meta Graph API el estado del número de WhatsApp.
    Retorna el estado de verificación por código (SMS/voz), nombre para mostrar y estado general (PENDING/CONNECTED).
    """
    token = _get_token(access_token)
    url = f"https://graph.facebook.com/{GRAPH_API_VERSION}/{phone_number_id}"
    params = {
        "fields": "verified_name,code_verification_status,display_phone_number,quality_rating,status,name_status",
        "access_token": token
    }
    try:
        resp = requests.get(url, params=params, timeout=12)
        data = resp.json()
        return {
            "success": resp.status_code == 200,
            "status_code": resp.status_code,
            "data": data,
            "is_pending": data.get("status") == "PENDING",
            "is_connected": data.get("status") == "CONNECTED",
            "code_verification": data.get("code_verification_status"),
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


@server.tool()
def whatsapp_check_waba_status(
    waba_id: str = DEFAULT_WABA_ID,
    access_token: str = ""
) -> Dict[str, Any]:
    """
    Consulta el estado de revisión de la cuenta comercial WABA (WhatsApp Business Account) en Meta.
    Indica si Meta aún tiene la cuenta en revisión ('account_review_status': 'PENDING') o si ya fue aprobada.
    """
    token = _get_token(access_token)
    url = f"https://graph.facebook.com/{GRAPH_API_VERSION}/{waba_id}"
    params = {
        "fields": "id,name,account_review_status,status",
        "access_token": token
    }
    try:
        resp = requests.get(url, params=params, timeout=12)
        data = resp.json()
        review_status = data.get("account_review_status", "UNKNOWN")
        return {
            "success": resp.status_code == 200,
            "status_code": resp.status_code,
            "data": data,
            "account_review_status": review_status,
            "ready_for_registration": review_status == "APPROVED" or (review_status not in ["PENDING", "FAILED"])
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


@server.tool()
def whatsapp_register_number(
    phone_number_id: str = DEFAULT_PHONE_NUMBER_ID,
    pin: str = "123456",
    access_token: str = ""
) -> Dict[str, Any]:
    """
    Ejecuta la llamada criptográfica POST /register ante Meta Graph API con un PIN de 6 dígitos.
    Este es el paso técnico que activa el contenedor del número y lo pasa de 'PENDING' a 'CONNECTED'.
    """
    token = _get_token(access_token)
    url = f"https://graph.facebook.com/{GRAPH_API_VERSION}/{phone_number_id}/register"
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }
    payload = {
        "messaging_product": "whatsapp",
        "pin": pin
    }
    try:
        resp = requests.post(url, headers=headers, json=payload, timeout=15)
        data = resp.json()
        is_success = resp.status_code == 200 and data.get("success") is True
        return {
            "success": is_success,
            "status_code": resp.status_code,
            "data": data,
            "message": "Registro completado exitosamente en Meta" if is_success else "Meta rechazó el registro. Revisa el mensaje de error."
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


@server.tool()
def whatsapp_send_test_message(
    to: str,
    text: str = "Hola, este es un mensaje de prueba desde XpertiFlow CRM Cloud API!",
    phone_number_id: str = DEFAULT_PHONE_NUMBER_ID,
    access_token: str = ""
) -> Dict[str, Any]:
    """
    Envía un mensaje de texto saliente por WhatsApp Cloud API usando la línea configurada.
    'to': Número destinatario en formato internacional con código de país (ej: '59179326793').
    """
    token = _get_token(access_token)
    clean_to = "".join(filter(str.isdigit, to))
    url = f"https://graph.facebook.com/{GRAPH_API_VERSION}/{phone_number_id}/messages"
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }
    payload = {
        "messaging_product": "whatsapp",
        "to": clean_to,
        "type": "text",
        "text": {"body": text}
    }
    try:
        resp = requests.post(url, headers=headers, json=payload, timeout=15)
        data = resp.json()
        return {
            "success": resp.status_code == 200,
            "status_code": resp.status_code,
            "data": data
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


@server.tool()
def crm_list_whatsapp_accounts() -> Dict[str, Any]:
    """
    Lista las cuentas y canales de WhatsApp registradas en la base de datos de XpertiFlow CRM (PostgreSQL).
    """
    cmd = [
        "docker", "compose", "exec", "-T", "api",
        "php", "artisan", "tinker",
        "--execute=echo json_encode(\\App\\Modules\\WhatsApp\\Models\\WhatsAppAccount::all(['id', 'name', 'phone_number_id', 'display_phone_number', 'session_type', 'status', 'is_active'])->toArray());"
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
        raw_output = result.stdout.strip()
        if "failed to connect to the docker API" in result.stderr:
            return {
                "success": False,
                "error": "Docker Desktop no está en ejecución. Por favor inicia Docker para consultar la base de datos del CRM."
            }
        start_idx = raw_output.find("[")
        end_idx = raw_output.rfind("]")
        if start_idx != -1 and end_idx != -1:
            accounts = json.loads(raw_output[start_idx:end_idx + 1])
            return {"success": True, "accounts": accounts}
        return {"success": False, "raw_output": raw_output, "error": result.stderr}
    except Exception as e:
        return {"success": False, "error": str(e)}


@server.tool()
def crm_update_whatsapp_status(
    phone_number_id: str = DEFAULT_PHONE_NUMBER_ID,
    new_status: str = "CONNECTED"
) -> Dict[str, Any]:
    """
    Actualiza el estado de la cuenta de WhatsApp en la base de datos de XpertiFlow CRM (ej: PENDING o CONNECTED).
    """
    php_code = f"""
    \\App\\Modules\\WhatsApp\\Models\\WhatsAppAccount::where('phone_number_id', '{phone_number_id}')
        ->update(['status' => '{new_status}', 'updated_at' => now()]);
    echo 'OK';
    """
    cmd = [
        "docker", "compose", "exec", "-T", "api",
        "php", "artisan", "tinker",
        f"--execute={php_code}"
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
        if "failed to connect to the docker API" in result.stderr:
            return {
                "success": False,
                "error": "Docker Desktop no está en ejecución. Por favor inicia Docker para actualizar la base de datos del CRM."
            }
        return {
            "success": "OK" in result.stdout,
            "output": result.stdout.strip(),
            "new_status": new_status
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


if __name__ == "__main__":
    # Iniciar servidor MCP en modo Stdio
    server.run(transport="stdio")
