# Conditions d'Avancement de Grade — Système Actuel

## Conditions de base (communes à toutes les promotions sous-officiers)
- ❌ Pas de problème **disciplinaire**
- ❌ Pas de problème **judiciaire**
- ❌ Pas **déserteur**
- 📋 Statut **actif**

---

## Sous-Officiers

### Soldat 1 → Caporal
| Critère | Condition |
|---|---|
| Ancienneté de service | ≥ **5 ans** (depuis la date d'entrée en service) |
| Certificat requis | **CAT1** (Certificat d'Aptitude Technique Niveau 1) |
| Conditions de base | ✅ |

### Caporal → Sergent
| Critère | Condition |
|---|---|
| Certificats requis | **CAT1** + **CAT2** (les deux) |
| Ancienneté dans le certificat | 3 ans après obtention du CAT1 |
| Conditions de base | ✅ |

### Caporal → Caporal-Chef *(voie alternative)*
| Critère | Condition |
|---|---|
| Certificat requis | **CAT1** obtenu |
| Certificat **NON** obtenu | **CAT2** (n'a pas le CAT2) |
| Âge minimum | ≥ **47 ans** |
| Ancienneté dans le grade | ≥ **3 ans** comme Caporal |
| Conditions de base | ✅ |

> [!NOTE]
> C'est une voie de sortie honorable pour les Caporaux qui n'ont pas réussi à obtenir le CAT2 et qui ont atteint 47 ans.

### Sergent → Sergent-Chef
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **2 ans** comme Sergent |
| Ancienneté de service | ≥ **5 ans** |
| Conditions de base | ✅ |

### Sergent-Chef → Adjudant
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **3 ans** comme Sergent-Chef |
| Conditions de base | ✅ |

### Adjudant → Adjudant-Chef
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **2 ans** comme Adjudant |
| Conditions de base | ✅ |

---

## Officiers

### Sous-Lieutenant → Lieutenant
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **2 ans** comme Sous-lieutenant |

### Lieutenant → Capitaine
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **3 ans** comme Lieutenant |

### Capitaine → Commandant
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **3 ans** comme Capitaine |

### Commandant → Lieutenant-Colonel
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **3 ans** comme Commandant |

### Lieutenant-Colonel → Colonel
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **3 ans** comme Lieutenant-colonel |

### Colonel → Colonel-Major
| Critère | Condition |
|---|---|
| Ancienneté dans le grade | ≥ **6 ans** comme Colonel |

---

## Résumé visuel de la hiérarchie

```mermaid
graph TD
    S1["Soldat 1"] -->|5 ans + CAT1| CPL["Caporal"]
    CPL -->|CAT1 + CAT2 + 3 ans| SGT["Sergent"]
    CPL -->|CAT1 sans CAT2 + 47 ans + 3 ans grade| CPLC["Caporal-Chef ⚡"]
    SGT -->|2 ans grade + 5 ans service| SGTC["Sergent-Chef"]
    SGTC -->|3 ans grade| ADJ["Adjudant"]
    ADJ -->|2 ans grade| ADJC["Adjudant-Chef"]

    SLT["Sous-Lieutenant"] -->|2 ans grade| LT["Lieutenant"]
    LT -->|3 ans grade| CPT["Capitaine"]
    CPT -->|3 ans grade| CDT["Commandant"]
    CDT -->|3 ans grade| LTCOL["Lieutenant-Colonel"]
    LTCOL -->|3 ans grade| COL["Colonel"]
    COL -->|6 ans grade| COLM["Colonel-Major"]

    style CPLC fill:#f9a825,stroke:#f57f17
```

> [!IMPORTANT]
> **Calcul de la date d'ancienneté :**
> - Pour Soldat 1 → Caporal : basé sur la **date d'entrée en service** + 5 ans
> - Pour tous les autres grades : basé sur la **date de dernière promotion** + X ans
