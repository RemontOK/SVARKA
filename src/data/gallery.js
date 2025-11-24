// Утилита для получения правильных путей к изображениям
const getImagePath = (path) => {
  const baseUrl = import.meta.env.BASE_URL || '/'
  const cleanPath = path.startsWith('/') ? path.slice(1) : path
  return `${baseUrl}${cleanPath}`
}

export const FIELD_SHOTS = [
  {
    id: 'bus-retrofit',
    image: getImagePath('/welding-1.jpg'),
    title: 'Импортозамещение электрооборудования',
    subtitle: 'Передвижные мастерские · MIG/MAG 250A',
    stats: ['48 часов запуск', '3 поста', '380В'],
  },
  {
    id: 'shipyard-night',
    image: getImagePath('/welding-2.jpg'),
    title: 'Ночная смена на судоверфи',
    subtitle: 'TIG AC/DC + плазморезы Hypertherm',
    stats: ['6 технологов', '24/7', 'NDT контроль'],
  },
  {
    id: 'architectural',
    image: getImagePath('/welding-3.jpg'),
    title: 'Архитектурные конструкции',
    subtitle: 'TIG по нержавейке и алюминию',
    stats: ['Шов до 0.6 мм', 'ЧПУ кондуктор', 'SLA 2 часа'],
  },
  {
    id: 'heavy-duty',
    image: getImagePath('/welding-4.jpg'),
    title: 'Ремонт карьерной техники',
    subtitle: 'MMA 400A + подогрев кромок',
    stats: ['80 м шва', 'Инженер на площадке', 'Гарантия 3 года'],
  },
]

