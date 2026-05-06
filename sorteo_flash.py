import random


TOTAL_NUMEROS = 42
PRECIO_NUMERO = 7000
PREMIOS = [
    ("Taladro inalambrico", 160000),
    ("Cafetera electrica", 70000),
]


participantes = {}


def formatear_guaranies(monto):
    return f"{monto:,}".replace(",", ".") + " gs"


def mostrar_premios():
    print("\nPremios:")
    for indice, (nombre, valor) in enumerate(PREMIOS, start=1):
        print(f"{indice}. {nombre} - valor {formatear_guaranies(valor)}")


def listar_numeros():
    disponibles = []
    ocupados = []

    for numero in range(1, TOTAL_NUMEROS + 1):
        if numero in participantes:
            ocupados.append(numero)
        else:
            disponibles.append(numero)

    print("\nNumeros disponibles:")
    print(", ".join(map(str, disponibles)) if disponibles else "No hay numeros disponibles.")

    print("\nNumeros ocupados:")
    if not ocupados:
        print("No hay numeros ocupados.")
        return

    for numero in ocupados:
        nombre = participantes[numero]
        print(f"Numero {numero}: {nombre}")


def pedir_numero():
    entrada = input(f"Numero elegido (1-{TOTAL_NUMEROS}): ").strip()

    if not entrada.isdigit():
        print("Error: debe ingresar un numero valido.")
        return None

    numero = int(entrada)
    if numero < 1 or numero > TOTAL_NUMEROS:
        print(f"Error: el numero debe estar entre 1 y {TOTAL_NUMEROS}.")
        return None

    return numero


def registrar_participante():
    print("\nRegistrar participante")
    nombre = input("Nombre del participante: ").strip()

    if not nombre:
        print("Error: el nombre no puede estar vacio.")
        return

    numero = pedir_numero()
    if numero is None:
        return

    if numero in participantes:
        print(f"Error: el numero {numero} ya esta ocupado por {participantes[numero]}.")
        return

    participantes[numero] = nombre
    print(f"Participante registrado: {nombre} con el numero {numero}.")


def realizar_sorteo():
    print("\nSorteo final")

    if not participantes:
        print("No hay participantes registrados. No se puede realizar el sorteo.")
        return

    numero_ganador = random.choice(list(participantes.keys()))
    nombre_ganador = participantes[numero_ganador]

    print("Ganador:")
    print(f"Numero: {numero_ganador}")
    print(f"Nombre: {nombre_ganador}")


def mostrar_resumen():
    vendidos = len(participantes)
    disponibles = TOTAL_NUMEROS - vendidos
    recaudado = vendidos * PRECIO_NUMERO

    print("\nResumen del sorteo FLASH")
    print(f"Total de numeros: {TOTAL_NUMEROS}")
    print(f"Precio por numero: {formatear_guaranies(PRECIO_NUMERO)}")
    print(f"Numeros vendidos: {vendidos}")
    print(f"Numeros disponibles: {disponibles}")
    print(f"Recaudacion actual: {formatear_guaranies(recaudado)}")


def mostrar_menu():
    print("\n=== SISTEMA DE SORTEO FLASH ===")
    print("1. Registrar participante")
    print("2. Listar numeros disponibles y ocupados")
    print("3. Realizar sorteo aleatorio")
    print("4. Ver premios")
    print("5. Ver resumen")
    print("0. Salir")


def main():
    mostrar_premios()

    while True:
        mostrar_menu()
        opcion = input("Seleccione una opcion: ").strip()

        if opcion == "1":
            registrar_participante()
        elif opcion == "2":
            listar_numeros()
        elif opcion == "3":
            realizar_sorteo()
        elif opcion == "4":
            mostrar_premios()
        elif opcion == "5":
            mostrar_resumen()
        elif opcion == "0":
            print("Saliendo del sistema.")
            break
        else:
            print("Opcion invalida. Intente nuevamente.")


if __name__ == "__main__":
    main()
