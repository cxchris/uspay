import { fileURLToPath } from 'url';
import path from 'path';


function getfilename(currentFileUrl){
  const currentFilePath = fileURLToPath(currentFileUrl);
  const fileName = path.basename(currentFilePath, path.extname(currentFilePath));

  return fileName;
}
export default getfilename;