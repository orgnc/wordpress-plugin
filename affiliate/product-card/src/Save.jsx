import { useBlockProps } from '@wordpress/block-editor';

import { AttributesType } from '../propTypes';
import ProductCard from './ProductCard';

const Save = ({ attributes }) => {
  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps.save()}>
      {attributes?.productGuid
        && (
        <ProductCard
          backgroundColor={attributes.backgroundColor}
          displayDescription={attributes.displayDescription}
          displayImage={attributes.displayImage}
          linkColor={attributes.linkColor}
          productGuid={attributes.productGuid}
          textColor={attributes.textColor}
        />
        )}
    </div>
  );
};

Save.propTypes = {
  attributes: AttributesType.isRequired,
};

export default Save;
